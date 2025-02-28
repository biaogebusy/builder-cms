<?php

namespace Drupal\image_effects\Component;

/**
 * Text handling methods for image_effects.
 */
abstract class TextUtility {

  /**
   * Matches all 'P' Unicode character classes (punctuation)
   */
  const PREG_CLASS_PUNCTUATION = <<< 'EOD'
\x{21}-\x{23}\x{25}-\x{2a}\x{2c}-\x{2f}\x{3a}\x{3b}\x{3f}\x{40}\x{5b}-\x{5d}
\x{5f}\x{7b}\x{7d}\x{a1}\x{ab}\x{b7}\x{bb}\x{bf}\x{37e}\x{387}\x{55a}-\x{55f}
\x{589}\x{58a}\x{5be}\x{5c0}\x{5c3}\x{5f3}\x{5f4}\x{60c}\x{60d}\x{61b}\x{61f}
\x{66a}-\x{66d}\x{6d4}\x{700}-\x{70d}\x{964}\x{965}\x{970}\x{df4}\x{e4f}
\x{e5a}\x{e5b}\x{f04}-\x{f12}\x{f3a}-\x{f3d}\x{f85}\x{104a}-\x{104f}\x{10fb}
\x{1361}-\x{1368}\x{166d}\x{166e}\x{169b}\x{169c}\x{16eb}-\x{16ed}\x{1735}
\x{1736}\x{17d4}-\x{17d6}\x{17d8}-\x{17da}\x{1800}-\x{180a}\x{1944}\x{1945}
\x{2010}-\x{2027}\x{2030}-\x{2043}\x{2045}-\x{2051}\x{2053}\x{2054}\x{2057}
\x{207d}\x{207e}\x{208d}\x{208e}\x{2329}\x{232a}\x{23b4}-\x{23b6}
\x{2768}-\x{2775}\x{27e6}-\x{27eb}\x{2983}-\x{2998}\x{29d8}-\x{29db}\x{29fc}
\x{29fd}\x{3001}-\x{3003}\x{3008}-\x{3011}\x{3014}-\x{301f}\x{3030}\x{303d}
\x{30a0}\x{30fb}\x{fd3e}\x{fd3f}\x{fe30}-\x{fe52}\x{fe54}-\x{fe61}\x{fe63}
\x{fe68}\x{fe6a}\x{fe6b}\x{ff01}-\x{ff03}\x{ff05}-\x{ff0a}\x{ff0c}-\x{ff0f}
\x{ff1a}\x{ff1b}\x{ff1f}\x{ff20}\x{ff3b}-\x{ff3d}\x{ff3f}\x{ff5b}\x{ff5d}
\x{ff5f}-\x{ff65}
EOD;

  /**
   * Matches all 'Z' Unicode character classes (separators)
   */
  const PREG_CLASS_SEPARATOR = <<< 'EOD'
\x{20}\x{a0}\x{1680}\x{180e}\x{2000}-\x{200a}\x{2028}\x{2029}\x{202f}
\x{205f}\x{3000}
EOD;

  /**
   * Unicode-safe preg_match().
   *
   * Search subject for a match to the regular expression given in pattern,
   * but return offsets in characters, where preg_match would return offsets
   * in bytes.
   *
   * @see http://php.net/manual/en/function.preg-match.php
   * @see http://drupal.org/node/465638
   */
  public static function unicodePregMatch($pattern, $subject, &$matches, $flags = 0, $offset = 0) {
    // Convert the offset value from characters to bytes.
    // NOTE - strlen is used on purpose here to get string length in bytes.
    // @see https://www.drupal.org/node/465638#comment-1600860
    $offset = strlen(mb_substr($subject, 0, $offset));

    $return_value = preg_match($pattern, $subject, $matches, $flags, $offset);

    if ($return_value && ($flags & PREG_OFFSET_CAPTURE)) {
      foreach ($matches as &$match) {
        // Convert the offset returned by preg_match from bytes back to
        // characters.
        // NOTE - substr is used on purpose here to get offset in bytes.
        // @see https://www.drupal.org/node/465638#comment-1600860
        $match[1] = mb_strlen(substr($subject, 0, $match[1]));
      }
    }
    return $return_value;
  }

}
