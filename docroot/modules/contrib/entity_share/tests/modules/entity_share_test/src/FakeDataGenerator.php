<?php

declare(strict_types = 1);

namespace Drupal\entity_share_test;

/**
 * Contains static methods from the Faker library.
 *
 * As the Faker library is deprecated, retrieve only the needed methods without
 * equivalent in Drupal tests.
 *
 * @see https://github.com/fzaninotto/faker
 *
 * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
 */
class FakeDataGenerator {

  /**
   * Default timezone.
   *
   * @var null
   */
  protected static $defaultTimezone = NULL;

  /**
   * The list of words to select from.
   *
   * @var string[]
   */
  protected static $wordList = [
    'alias', 'consequatur', 'aut', 'perferendis', 'sit', 'voluptatem',
    'accusantium', 'doloremque', 'aperiam', 'eaque', 'ipsa', 'quae', 'ab',
    'illo', 'inventore', 'veritatis', 'et', 'quasi', 'architecto',
    'beatae', 'vitae', 'dicta', 'sunt', 'explicabo', 'aspernatur', 'aut',
    'odit', 'aut', 'fugit', 'sed', 'quia', 'consequuntur', 'magni',
    'dolores', 'eos', 'qui', 'ratione', 'voluptatem', 'sequi', 'nesciunt',
    'neque', 'dolorem', 'ipsum', 'quia', 'dolor', 'sit', 'amet',
    'consectetur', 'adipisci', 'velit', 'sed', 'quia', 'non', 'numquam',
    'eius', 'modi', 'tempora', 'incidunt', 'ut', 'labore', 'et', 'dolore',
    'magnam', 'aliquam', 'quaerat', 'voluptatem', 'ut', 'enim', 'ad',
    'minima', 'veniam', 'quis', 'nostrum', 'exercitationem', 'ullam',
    'corporis', 'nemo', 'enim', 'ipsam', 'voluptatem', 'quia', 'voluptas',
    'sit', 'suscipit', 'laboriosam', 'nisi', 'ut', 'aliquid', 'ex', 'ea',
    'commodi', 'consequatur', 'quis', 'autem', 'vel', 'eum', 'iure',
    'reprehenderit', 'qui', 'in', 'ea', 'voluptate', 'velit', 'esse',
    'quam', 'nihil', 'molestiae', 'et', 'iusto', 'odio', 'dignissimos',
    'ducimus', 'qui', 'blanditiis', 'praesentium', 'laudantium', 'totam',
    'rem', 'voluptatum', 'deleniti', 'atque', 'corrupti', 'quos',
    'dolores', 'et', 'quas', 'molestias', 'excepturi', 'sint',
    'occaecati', 'cupiditate', 'non', 'provident', 'sed', 'ut',
    'perspiciatis', 'unde', 'omnis', 'iste', 'natus', 'error',
    'similique', 'sunt', 'in', 'culpa', 'qui', 'officia', 'deserunt',
    'mollitia', 'animi', 'id', 'est', 'laborum', 'et', 'dolorum', 'fuga',
    'et', 'harum', 'quidem', 'rerum', 'facilis', 'est', 'et', 'expedita',
    'distinctio', 'nam', 'libero', 'tempore', 'cum', 'soluta', 'nobis',
    'est', 'eligendi', 'optio', 'cumque', 'nihil', 'impedit', 'quo',
    'porro', 'quisquam', 'est', 'qui', 'minus', 'id', 'quod', 'maxime',
    'placeat', 'facere', 'possimus', 'omnis', 'voluptas', 'assumenda',
    'est', 'omnis', 'dolor', 'repellendus', 'temporibus', 'autem',
    'quibusdam', 'et', 'aut', 'consequatur', 'vel', 'illum', 'qui',
    'dolorem', 'eum', 'fugiat', 'quo', 'voluptas', 'nulla', 'pariatur',
    'at', 'vero', 'eos', 'et', 'accusamus', 'officiis', 'debitis', 'aut',
    'rerum', 'necessitatibus', 'saepe', 'eveniet', 'ut', 'et',
    'voluptates', 'repudiandae', 'sint', 'et', 'molestiae', 'non',
    'recusandae', 'itaque', 'earum', 'rerum', 'hic', 'tenetur', 'a',
    'sapiente', 'delectus', 'ut', 'aut', 'reiciendis', 'voluptatibus',
    'maiores', 'doloribus', 'asperiores', 'repellat',
  ];

  /**
   * Helper method.
   *
   * Get a DateTime object based on a random date between two given dates.
   * Accepts date strings that can be recognized by strtotime().
   *
   * @param \DateTime|string $startDate
   *   Defaults to 30 years ago.
   * @param \DateTime|string $endDate
   *   Defaults to "now".
   * @param string|null $timezone
   *   Time zone in which the date time should be set, default to
   *   DateTime::$defaultTimezone, if set, otherwise the result of
   *   `date_default_timezone_get`.
   *
   * @return \DateTime
   *   A DateTime object.
   *
   * @example DateTime('1999-02-02 11:42:52')
   *
   * @see http://php.net/manual/en/timezones.php
   * @see http://php.net/manual/en/function.date-default-timezone-get.php
   */
  public static function dateTimeBetween($startDate = '-30 years', $endDate = 'now', $timezone = NULL) {
    $startTimestamp = $startDate instanceof \DateTime ? $startDate->getTimestamp() : strtotime($startDate);
    $endTimestamp = static::getMaxTimestamp($endDate);

    if ($startTimestamp > $endTimestamp) {
      throw new \InvalidArgumentException('Start date must be anterior to end date.');
    }

    $timestamp = mt_rand($startTimestamp, $endTimestamp);

    return static::setTimezone(
      new \DateTime('@' . $timestamp),
      $timezone
    );
  }

  /**
   * Generate a single paragraph.
   *
   * @param int $nbSentences
   *   Around how many sentences the paragraph should contain.
   * @param bool $variableNbSentences
   *   Set to false if you want exactly $nbSentences returned,
   *   otherwise $nbSentences may vary by +/-40% with a minimum of 1.
   *
   * @return string
   *   A paragraph.
   *
   * @example 'Sapiente sunt omnis. Ut pariatur ad autem ducimus et. Voluptas
   * rem voluptas sint modi dolorem amet.'
   */
  public static function paragraph($nbSentences = 3, $variableNbSentences = TRUE) {
    if ($nbSentences <= 0) {
      return '';
    }

    if ($variableNbSentences) {
      $nbSentences = self::randomizeNbElements($nbSentences);
    }

    return implode(' ', static::sentences($nbSentences));
  }

  /**
   * Generate an array of paragraphs.
   *
   * @param int $numberToReturn
   *   How many paragraphs to return.
   * @param bool $asText
   *   If true the paragraphs are returned as one string, separated by two
   *   newlines.
   *
   * @return array|string
   *   The paragraphs.
   *
   * @example array($paragraph1, $paragraph2, $paragraph3)
   */
  public static function paragraphs($numberToReturn = 3, $asText = FALSE) {
    $paragraphs = [];

    for ($i = 0; $i < $numberToReturn; ++$i) {
      $paragraphs[] = static::paragraph();
    }

    return $asText ? implode("\n\n", $paragraphs) : $paragraphs;
  }

  /**
   * Returns a random number between 0 and 9.
   *
   * @return int
   *   A random digit.
   */
  public static function randomDigit() {
    return mt_rand(0, 9);
  }

  /**
   * Returns a random number between 1 and 9.
   *
   * @return int
   *   A random digit.
   */
  public static function randomDigitNotNull() {
    return mt_rand(1, 9);
  }

  /**
   * Returns a random element from a passed array.
   *
   * @param array $array
   *   An array of elements to select from.
   *
   * @return mixed
   *   A random element.
   */
  public static function randomElement(array $array = ['a', 'b', 'c']) {
    if (!$array || ($array instanceof \Traversable && !\count($array))) {
      return NULL;
    }
    $elements = static::randomElements($array, 1);

    return $elements[0];
  }

  /**
   * Randomly ordered subsequence of $count elements from a provided array.
   *
   * @param array $array
   *   Array to take elements from. Defaults to a-c.
   * @param int $count
   *   Number of elements to take.
   * @param bool $allowDuplicates
   *   Allow elements to be picked several times. Defaults to false.
   *
   * @return array
   *   New array with $count elements from $array.
   *
   * @throws \LengthException
   *   When requesting more elements than provided.
   */
  public static function randomElements(array $array = ['a', 'b', 'c'], $count = 1, $allowDuplicates = FALSE) {
    $traversables = [];

    if ($array instanceof \Traversable) {
      foreach ($array as $element) {
        $traversables[] = $element;
      }
    }

    $arr = \count($traversables) ? $traversables : $array;

    $allKeys = array_keys($arr);
    $numKeys = \count($allKeys);

    if (!$allowDuplicates && $numKeys < $count) {
      throw new \LengthException(sprintf('Cannot get %d elements, only %d in array', $count, $numKeys));
    }

    $highKey = $numKeys - 1;
    $keys = $elements = [];
    $numElements = 0;

    while ($numElements < $count) {
      $num = mt_rand(0, $highKey);

      if (!$allowDuplicates) {
        if (isset($keys[$num])) {
          continue;
        }
        $keys[$num] = TRUE;
      }

      $elements[] = $arr[$allKeys[$num]];
      ++$numElements;
    }

    return $elements;
  }

  /**
   * Return a random float number.
   *
   * @param int $nbMaxDecimals
   *   The maximum number of decimals.
   * @param float|int $min
   *   The min value.
   * @param float|int $max
   *   The max value.
   *
   * @return float
   *   A random float.
   *
   * @example 48.8932
   */
  public static function randomFloat($nbMaxDecimals = NULL, $min = 0, $max = NULL) {
    if ($nbMaxDecimals === NULL) {
      $nbMaxDecimals = static::randomDigit();
    }

    if ($max === NULL) {
      $max = static::randomNumber();

      if ($min > $max) {
        $max = $min;
      }
    }

    if ($min > $max) {
      $tmp = $min;
      $min = $max;
      $max = $tmp;
    }

    return round($min + mt_rand() / mt_getrandmax() * ($max - $min), $nbMaxDecimals);
  }

  /**
   * Returns a random integer with 0 to $nbDigits digits.
   *
   * The maximum value returned is mt_getrandmax()
   *
   * @param int $nbDigits
   *   Defaults to a random number between 1 and 9.
   * @param bool $strict
   *   Whether the returned number should have exactly $nbDigits.
   *
   * @return int
   *   A random number.
   *
   * @example 79907610
   */
  public static function randomNumber($nbDigits = NULL, $strict = FALSE) {
    if (!\is_bool($strict)) {
      throw new \InvalidArgumentException('randomNumber() generates numbers of fixed width. To generate numbers between two boundaries, use numberBetween() instead.');
    }

    if ($nbDigits === NULL) {
      $nbDigits = static::randomDigitNotNull();
    }
    $max = 10 ** $nbDigits - 1;

    if ($max > mt_getrandmax()) {
      throw new \InvalidArgumentException('randomNumber() can only generate numbers up to mt_getrandmax()');
    }

    if ($strict) {
      return mt_rand(10 ** ($nbDigits - 1), $max);
    }

    return mt_rand(0, $max);
  }

  /**
   * Generate a random sentence.
   *
   * @param int $nbWords
   *   Around how many words the sentence should contain.
   * @param bool $variableNbWords
   *   Set to false if you want exactly $nbWords returned,
   *   otherwise $nbWords may vary by +/-40% with a minimum of 1.
   *
   * @return string
   *   The sentence.
   *
   * @example 'Lorem ipsum dolor sit amet.'
   */
  public static function sentence($nbWords = 6, $variableNbWords = TRUE) {
    if ($nbWords <= 0) {
      return '';
    }

    if ($variableNbWords) {
      $nbWords = self::randomizeNbElements($nbWords);
    }

    $words = static::words($nbWords);
    $words[0] = ucwords($words[0]);

    return implode(' ', $words) . '.';
  }

  /**
   * Generate an array of sentences.
   *
   * @param int $numberToReturn
   *   How many sentences to return.
   * @param bool $asText
   *   If true the sentences are returned as one string.
   *
   * @return array|string
   *   The sentences.
   *
   * @example ['Lorem ipsum dolor sit amet.', 'Consectetur adipisicing eli.']
   */
  public static function sentences($numberToReturn = 3, $asText = FALSE) {
    $sentences = [];

    for ($i = 0; $i < $numberToReturn; ++$i) {
      $sentences[] = static::sentence();
    }

    return $asText ? implode(' ', $sentences) : $sentences;
  }

  /**
   * Generate a text string.
   *
   * Depending on the $maxNbChars, returns a string made of words, sentences,
   * or paragraphs.
   *
   * @param int $maxNbChars
   *   Maximum number of characters the text should contain (minimum 5).
   *
   * @return string
   *   The text.
   *
   * @example 'Sapiente sunt omnis. Ut pariatur ad autem ducimus et. Voluptas
   * rem voluptas sint modi dolorem amet.'
   */
  public static function text($maxNbChars = 200) {
    if ($maxNbChars < 5) {
      throw new \InvalidArgumentException('text() can only generate text of at least 5 characters');
    }

    $type = ($maxNbChars < 25) ? 'word' : (($maxNbChars < 100) ? 'sentence' : 'paragraph');

    $text = [];

    while (empty($text)) {
      $size = 0;

      // Until $maxNbChars is reached.
      while ($size < $maxNbChars) {
        $word = ($size ? ' ' : '') . static::$type();
        $text[] = $word;

        $size += \strlen($word);
      }

      array_pop($text);
    }

    if ($type === 'word') {
      // Capitalize first letter.
      $text[0] = ucwords($text[0]);

      // End sentence with full stop.
      $text[\count($text) - 1] .= '.';
    }

    return implode('', $text);
  }

  /**
   * Get a timestamp between January 1, 1970 and now.
   *
   * @param \DateTime|int|string $max
   *   Maximum timestamp used as random end limit, default to "now".
   *
   * @return int
   *   The unix timestamp.
   *
   * @example 1061306726
   */
  public static function unixTime($max = 'now') {
    return mt_rand(0, static::getMaxTimestamp($max));
  }

  /**
   * Get a word.
   *
   * @return string
   *   A word.
   *
   * @example 'Lorem'
   */
  public static function word() {
    return static::randomElement(static::$wordList);
  }

  /**
   * Generate an array of random words.
   *
   * @param int $numberToReturn
   *   How many words to return.
   * @param bool $asText
   *   If true the sentences are returned as one string.
   *
   * @return array|string
   *   The words.
   *
   * @example array('Lorem', 'ipsum', 'dolor')
   */
  public static function words($numberToReturn = 3, $asText = FALSE) {
    $words = [];

    for ($i = 0; $i < $numberToReturn; ++$i) {
      $words[] = static::word();
    }

    return $asText ? implode(' ', $words) : $words;
  }

  /**
   * Helper method.
   *
   * @param \DateTime|float|int|string $max
   *   The max timestamp.
   *
   * @return false|int
   *   The max timestamp.
   */
  protected static function getMaxTimestamp($max = 'now') {
    if (is_numeric($max)) {
      return (int) $max;
    }

    if ($max instanceof \DateTime) {
      return $max->getTimestamp();
    }

    return strtotime(empty($max) ? 'now' : $max);
  }

  /**
   * Helper method.
   *
   * @param int $nbElements
   *   The number of elements to select.
   *
   * @return int
   *   A randomized number.
   */
  protected static function randomizeNbElements($nbElements) {
    return (int) ($nbElements * mt_rand(60, 140) / 100) + 1;
  }

  /**
   * Helper method.
   *
   * @param string|null $timezone
   *   The timezone to set.
   *
   * @return string|null
   *   The found timezone.
   */
  private static function resolveTimezone($timezone) {
    return ($timezone === NULL) ? ((static::$defaultTimezone === NULL) ? date_default_timezone_get() : static::$defaultTimezone) : $timezone;
  }

  /**
   * Internal method to set the time zone on a DateTime.
   *
   * @param \DateTime $datetime
   *   The DateTime object.
   * @param string|null $timezone
   *   The timezone to set.
   *
   * @return \DateTime
   *   The Datetime object with modified timezone.
   */
  private static function setTimezone(\DateTime $datetime, $timezone) {
    return $datetime->setTimezone(new \DateTimeZone(static::resolveTimezone($timezone)));
  }

}
