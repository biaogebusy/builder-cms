<?php

namespace MakinaCorpus\Lucene\Tests;

use MakinaCorpus\Lucene\AbstractQuery;
use MakinaCorpus\Lucene\Query;
use MakinaCorpus\Lucene\TermQuery;
use PHPUnit\Framework\TestCase;
use DateTime;

/**
 * @coversDefaultClass \MakinaCorpus\Lucene\Query
 * @coversDefaultClass \MakinaCorpus\Lucene\AbstractQuery
 */
class BasicQueryTest extends TestCase
{
    public function testSomeQuery()
    {
        $query = new Query();

        // Avoid runtime timezone conversion given false negative.
        $from = new DateTime("1983-03-22", new \DateTimeZone("UTC"));
        $now = new DateTime("@1467205820", new \DateTimeZone("UTC"));

        $query
            ->setOperator(Query::OP_AND)
            ->createTermCollection(Query::OP_OR)
            ->add("foo")
            ->add("bar");

        $query
            ->createDateRange('my_date_field')
            ->setInclusive()
            ->setRange($from, $now);

        $query
            ->matchTerm('some_field', 'some value', null, 0.8)
            ->matchTerm('other_field', 'some_other_value', 1.3);

        // @codingStandardsIgnoreStart
        self::assertSame(
            '((foo OR bar) AND my_date_field:["1983\-03\-22T00\:00\:00\+0000" TO "2016\-06\-29T13\:10\:20\+0000"] AND some_field:"some value"~0.8 AND other_field:some_other_value^1.3)',
            trim((string)$query)
        );
        // @codingStandardsIgnoreEnd
    }

    public function testDocumentationIssue7Legacy()
    {
        $query = new Query();
        $query->setOperator(Query::OP_AND);

        $query->createTerm()->setField('field 1')->setValue('abc');
        $query->createTerm()->setField('field 2')->setValue(123);

        $query
            ->createTermCollection(Query::OP_OR)
            ->add(
                (new TermQuery())
                    ->setField('field 3')
                    ->setValue('a')
            )
            ->add(
                (new TermQuery())
                    ->setField('field 3')
                    ->setValue('b')
            )
        ;

        self::assertSame(
            '("field 1":abc AND "field 2":123 AND ("field 3":a OR "field 3":b))',
            \trim((string) $query)
        );
    }

    public function testDocumentationIssue7Best()
    {
        $query = new Query();
        $query->setOperator(Query::OP_AND);

        $query->createTerm()->setField('field 1')->setValue('abc');
        $query->createTerm()->setField('field 2')->setValue(123);

        $or = $query->createTermCollection(Query::OP_OR);
        $or->setField("field 3");
        $or->createTerm()->setValue('a');
        $or->createTerm()->setValue('b');

        self::assertSame(
            '("field 1":abc AND "field 2":123 AND "field 3":(a OR b))',
            \trim((string) $query)
        );
    }

    public function testDocumentationIssue7Possible()
    {
        $query = new Query();
        $query->setOperator(Query::OP_AND);

        $query->createTerm()->setField('field 1')->setValue('abc');
        $query->createTerm()->setField('field 2')->setValue(123);

        $or = $query->createTermCollection(Query::OP_OR);
        $or->createTerm()->setField('field 3')->setValue('a');
        $or->createTerm()->setField('field 3')->setValue('b');

        self::assertSame(
            '("field 1":abc AND "field 2":123 AND ("field 3":a OR "field 3":b))',
            \trim((string) $query)
        );
    }

    public function testDocumentationIssue9()
    {
        $query = new Query();
        $collection = $query->createTermCollection();

        $collection->requireTerm(null, 'foo');
        $collection->prohibitTerm(null, 'bar');
        $collection->matchTermCollection(null, ['fizz', 'buzz']);

        $collection->requireTermCollection(null, ['a', 'b']);

        self::assertSame(
            '(+foo AND -bar AND (fizz OR buzz) AND +(a OR b))',
            \trim((string) $query)
        );
    }

    public function dataSpecialCharacterEscaping()
    {
        return [
            [
                'Use the "~" symbol to add boost to your fields!',
                'Use the \"\~\" symbol to add boost to your fields\!',
            ],
            [
                'Some date formats are: \"(30/12/1993), {30-12-1993} & [30\\12\\1993]\"',
                'Some date formats are\: \\\\\"\(30\/12\/1993\), \{30\-12\-1993\} \& \[30\\\12\\\1993\]\\\\\"',
            ],
            [
                'one + one = two',
                'one \+ one \= two',
            ],
            [
                'if (something || (something_else && another_something))',
                'if \(something \|\| \(something_else \&\& another_something\)\)',
            ],
            [
                'Here is a cat: ^*>.<*^!!',
                'Here is a cat\: \^\*\>.\<\*\^\!\!',
            ],
            [
                'Couldn\'t you come up with better test Strings? ... NO, sorry!',
                'Couldn\'t you come up with better test Strings\? ... NO, sorry\!',
            ],
        ];
    }

    /**
     * @dataProvider dataSpecialCharacterEscaping
     */
    public function testSpecialCharacterEscaping(string $raw, string $expected)
    {
        self::assertSame('"' . $expected . '"', AbstractQuery::escapeToken($raw));
    }

    public function testEscapeSingleWordNoQuotes()
    {
        self::assertSame('Word', AbstractQuery::escapeToken('Word'));
    }

    public function testEscapePhraseAddQuotes()
    {
        self::assertSame('"Random search phrase."', AbstractQuery::escapeToken('Random search phrase.'));
    }

    public function testEscapeSingleWordAddQuoteWithForceParam()
    {
        self::assertSame('"Word"', AbstractQuery::escapeToken('Word', true));
    }

    public function testEscapeWithSpecialCharsAddQuotes()
    {
        self::assertSame('"\!\*Test.php, \*.php"', AbstractQuery::escapeToken('!*Test.php, *.php'));
    }
}
