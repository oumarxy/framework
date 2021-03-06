<?php

use Bow\Security\Hash;
use Bow\Security\Crypto;

class SecurityTest extends \PHPUnit\Framework\TestCase
{
    public function depGetHashValue()
    {
        return Hash::create('bow');
    }

    public function depGetEncryptValue()
    {
        Crypto::setKey(file_get_contents(__DIR__.'/config/.key'), 'AES-256-CBC');

        return Crypto::encrypt('bow');
    }

    /**
     * @depends depGetEncryptValue
     * @param $data
     */
    public function testShouldDecryptData($data)
    {
        Crypto::setkey(file_get_contents(__DIR__.'/config/.key'), 'AES-256-CBC');

        $this->assertEquals(Crypto::decrypt($data), 'bow');
    }

    /**
     * @depends depGetHashValue
     * @param $data
     */
    public function testShouldCheckHashValue($data)
    {
        $this->assertTrue(Hash::check('bow', $data));
    }
}
