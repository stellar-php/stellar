<?php declare(strict_types=1);

namespace Stellar\Limitations\Testing;

use Stellar\Limitations\Exceptions\UnserializationProhibited;

/**
 * PHPUnit assertion for variables that should not be allowed to be unserialized.
 *
 * @see:unit-test \UnitTests\Limitations\Testing\AssertProhibitUnserializationTests
 */
trait AssertProhibitUnserialization
{
    /**
     * @param object $var
     */
    public function assertProhibitUnserialization($var) : void
    {
        $this->expectException(UnserializationProhibited::class);
        $this->assertException(function () use ($var) {
            $var->unserialize('');
        });
    }
}
