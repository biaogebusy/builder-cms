<?php

namespace AlibabaCloud\Dara\Util;

use GuzzleHttp\Psr7\Stream;
use AlibabaCloud\Dara\Util\StringUtil;
use AlibabaCloud\Dara\Models\SSEEvent;

class StreamUtil
{
    /**
     * @param Stream $stream
     *
     * @return int[]
     */
    public static function readAsBytes($stream)
    {
        $str = self::readAsString($stream);

        return StringUtil::toBytes($str);
    }

    /**
     * @param Stream $stream
     *
     * @return array the parsed result
     */
    public static function readAsJSON($stream)
    {
        $jsonString = self::readAsString($stream);

        return json_decode($jsonString, true);
    }

    /**
     * @param Stream $stream
     *
     * @return string
     */
    public static function readAsString($stream)
    {
        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        return $stream->getContents();
    }

    private static function tryGetEvents($head, $chunk) {
        $all = $head . $chunk;
        if(empty($all)) {
            return [];
        }
        $start = 0;
        $events = [];
        $lines = explode("\n", $all);
        $event = new SSEEvent();
        for ($i = 0; $i < strlen($all) - 1; $i++) {
            $c = $all[$i];
            $c2 = $all[$i + 1];
            if ($c === "\n" && $c2 === "\n") {
                $part = substr($all, $start, $i - $start);
                $lines = explode("\n", $part);
                $event = new SSEEvent();
                
                foreach ($lines as $line) {
                    if ('' === trim($line)) {
                        continue;
                    } elseif (0 === strpos($line, 'data:')) {
                        $data = substr($line, 5);
                        $event->data .= trim($data);
                    } elseif (0 === strpos($line, 'event:')) {
                        $eventLine = substr($line, 6);
                        $event->event = trim($eventLine);
                    } elseif (0 === strpos($line, 'id:')) {
                        $id = substr($line, 3);
                        $event->id = trim($id);
                    } elseif (0 === strpos($line, 'retry:')) {
                        $retry = substr($line, 6);
                        $retry = trim($retry);
                        if (ctype_digit($retry)) {
                            $event->retry = intval($retry, 10);
                        }
                    } elseif (isset($line[0]) && $line[0] === ':') {
                         // Lines starting with ':' are comments and ignored.
                    }
                }
                array_push($events, $event);
                $start = $i + 2;
            }
        }
        $remain = substr($all, $start);
        return ['events' => $events, 'remain' => $remain];
    }

    /**
     * @param Stream $stream
     *
     * @return string
     */
    public static function readAsSSE($stream)
    {
        $rest = '';
        while (!$stream->eof()) {
            $chunk = $stream->read(4096); 
            $result = self::tryGetEvents($rest, $chunk);
            if(empty($result)) {
                continue;
            }
            $events = $result['events'];
            $rest = $result['remain'];

            foreach ($events as $event) {
                yield $event;
            }
        }

        // If there is any remaining data that qualifies as an event, yield it as well
        if ($rest !== '' && $rest !== false) {
            $lastEvent = new SSEEvent();
            $lastEvent->data = $rest;
            yield $lastEvent;
        }
    }

    /**
     * Create and return a Guzzle Stream from various inputs.
     *
     * @param mixed $str  string|resource|callable|\GuzzleHttp\Psr7\Stream|object
     * @return Stream
     * @throws \InvalidArgumentException|\RuntimeException
     */
    public static function streamFor($str)
    {
        if (!class_exists('\GuzzleHttp\Psr7\Stream')) {
            throw new \RuntimeException('guzzlehttp/psr7 is required for streamFor');
        }

        // Already a Guzzle Stream
        if ($str instanceof Stream) {
            return $str;
        }

        // If callable, call and recurse
        if (is_callable($str)) {
            return self::streamFor($str());
        }

        // If resource (stream), wrap directly
        if (is_resource($str)) {
            if (get_resource_type($str) !== 'stream') {
                throw new \InvalidArgumentException('Provided resource is not a stream');
            }
            return new Stream($str);
        }

        // If object with __toString, cast it
        if (is_object($str) && method_exists($str, '__toString')) {
            $str = (string)$str;
        }

        // Must be string (or scalar convertible)
        if (!is_string($str) && !is_scalar($str) && !is_null($str)) {
            throw new \InvalidArgumentException('Unsupported type for streamFor: ' . gettype($str));
        }

        $content = $str === null ? '' : (string)$str;

        $resource = @fopen('php://temp', 'w+b');
        if ($resource === false) {
            throw new \RuntimeException('Failed to open temporary stream');
        }

        if ($content !== '') {
            fwrite($resource, $content);
            rewind($resource);
        }

        return new Stream($resource);
    }
    
}
