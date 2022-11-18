<?php

namespace Tools\Prepare;
use Tools\Config;
use PDO;

class DB extends Config
{
	protected function DB()
	{
		$dsn = "mysql:host={$this->db['host']};dbname={$this->db['db']};charset=utf8mb4";

		$opt = [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		];

		$pdo = new PDO($dsn, $this->db['user'], $this->db['pass'], $opt);
		return $pdo;
	}
}