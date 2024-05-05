<?php

namespace App\Base;

class TokenGenerator
{
	const ALGO = 'sha512';
	const HASH_PATTERN = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	const HASH_LENGTH = 64;

	private string $secret = '';
	private string $algo = self::ALGO;

	public function __construct(string $algo = self::ALGO)
	{
		$this->secret = Config::init()->get('secret');
		$this->algo = $algo;
	}

	public function getHash(
		?int $length = self::HASH_LENGTH,
		?string $pattern = self::HASH_PATTERN
	): string
	{
			$result = '';
			$pattern_length = strlen($pattern);

			for ($i = 0; $i < $length; $i++){
				$result .= $pattern[rand(0, $pattern_length - 1)];
			}

			return $result;
	}

	public function getSecret(string $token): string
	{
		return hash($this->algo, $this->secret . $token);
	}

	public function getUUID(): string
	{
		return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			// 32 bits for "time_low"
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

			// 16 bits for "time_mid"
			mt_rand( 0, 0xffff ),

			// 16 bits for "time_hi_and_version",
			// four most significant bits holds version number 4
			mt_rand( 0, 0x0fff ) | 0x4000,

			// 16 bits, 8 bits for "clk_seq_hi_res",
			// 8 bits for "clk_seq_low",
			// two most significant bits holds zero and one for variant DCE1.1
			mt_rand( 0, 0x3fff ) | 0x8000,

			// 48 bits for "node"
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
		);
	}
}