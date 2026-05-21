<?php

namespace AlibabaCloud\Dara\Util;

use Psr\Http\Message\StreamInterface;

/**
 * PHP 7.1+ version with return type declarations for PSR-7 v2.0 compatibility
 * @internal
 * @coversNothing
 */
class FileFormStreamTyped implements StreamInterface
{
    use FileFormStreamTrait;

    public function __toString(): string { return $this->__toStringImpl(); }
    public function getContents(): string { return $this->getContentsImpl(); }
    public function close(): void { $this->closeImpl(); }
    public function detach() { return $this->detachImpl(); }
    public function getSize(): ?int { return $this->getSizeImpl(); }
    public function tell(): int { return $this->tellImpl(); }
    public function eof(): bool { return $this->eofImpl(); }
    public function isSeekable(): bool { return $this->isSeekableImpl(); }
    public function seek($offset, $whence = SEEK_SET): void { $this->seekImpl($offset, $whence); }
    public function rewind(): void { $this->rewindImpl(); }
    public function isWritable(): bool { return $this->isWritableImpl(); }
    public function write($string): int { return $this->writeImpl($string); }
    public function isReadable(): bool { return $this->isReadableImpl(); }
    public function read($length): string { return $this->readImpl($length); }
    public function getMetadata($key = null) { return $this->getMetadataImpl($key); }
}
