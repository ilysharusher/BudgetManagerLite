<?php

namespace Tools\Do;

use Tools\Config;
use Tools\Prepare\DB;

class DbQuery extends DB
{
	public static function update_currency()
	{
		$pdo = (new Db)->Db();

		$currency = json_decode(file_get_contents(Config::CURRENCY), true);

		$USD = $currency[0]['rateSell'];
		$EUR = $currency[1]['rateSell'];

		$stmt = $pdo->prepare("UPDATE `mono_currency` SET `USD` = ?, `EUR` = ? WHERE `id` = ?");
		$stmt->execute([round($USD, 2), round($EUR, 2), 1]);
	}

	public static function get_categories($type)
	{
		$pdo = (new Db)->Db();
		$stmt = $pdo->prepare("SELECT * FROM `categories` WHERE `type` = ?");
		$stmt->execute([$type]);

		$arr = [];

		foreach ($stmt->fetchAll() as $item) {
			$arr[] = ['text' => "{$item['title']}", 'callback_data' => "{$item['id']}"];
		}
		array_splice($arr, $type == '+' ? 4 : 12, 0, [
			['text' => '⬅️ Назад', 'callback_data' => 'choose_input_type']
		]);

		return $arr;
	}

	public static function get_category($id = NULL, $type = NULL)
	{
		$pdo = (new Db)->Db();
		if ($type == NULL) {
			$stmt = $pdo->prepare("SELECT `title` FROM `categories` WHERE `id` = ?");
			$stmt->execute([$id]);
		} else {
			$stmt = $pdo->prepare("SELECT `title` FROM `categories` WHERE `id` = (SELECT `category` FROM `condition` WHERE `chat_id` = ?)");
			$stmt->execute([$id]);
		}

		return $stmt->fetchAll()[0]['title'];
	}

	public static function get_type($category)
	{
		$pdo = (new Db)->Db();
		$stmt = $pdo->prepare("SELECT `type` FROM `categories` WHERE title = ?");
		$stmt->execute([$category]);

		return $stmt->fetchAll()[0]['type'];
	}

	public static function condition($chat_id, $type, $description = NULL, $category = NULL, $amount = NULL, $first_name = NULL)
	{
		$pdo = (new Db)->Db();
		if ($type == 'get') {
			$stmt = $pdo->prepare("SELECT `type`, `amount`, `description`, `category` FROM `condition` WHERE `chat_id` = ?");
			$stmt->execute([$chat_id]);
		} elseif ($type == 'set') {
			$stmt = $pdo->prepare("UPDATE `condition` SET `type` = (SELECT `type` FROM `categories` WHERE `id` = ?), `category` = ?, `amount` = ?, `description` = ? WHERE `chat_id` = ?");
			$stmt->execute([$category, $category, $amount, $description, $chat_id]);
		} elseif ($type == 'add') {
			$stmt = $pdo->prepare("INSERT INTO `condition` (`chat_id`, `first_name`) VALUES (?, ?)");
			$stmt->execute([$chat_id, $first_name]);
		}

		$res = $stmt->fetchAll();

		return $type == 'get' ? $res[0] : true;
	}

	public static function add_description($chat_id, $description)
	{
		$pdo = (new Db)->Db();
		$stmt = $pdo->prepare("UPDATE `records` SET `description` = ? WHERE `chat_id` = ? ORDER BY id DESC LIMIT 1");
		$stmt->execute([$description, $chat_id]);
	}

	public static function add_record($chat_id, $first_name, $type, $amount, $currency, $category)
	{
		$pdo = (new Db)->Db();
		$stmt = $pdo->prepare("INSERT INTO `records` (`chat_id`, `first_name`, `type`, `amount`, `currency`, `category`) VALUES (?, ?, ?, ?, ?, ?)");
		$stmt->execute([$chat_id, $first_name, $type, $amount, $currency, $category]);
	}

	public static function more_results($chat_id, $type, $result_currency = NULL, $result_type = NULL, $result_interval = NULL)
	{
		$pdo = (new Db)->Db();

		if ($type == 'set') {
			$stmt = $pdo->prepare("UPDATE `condition` SET `result_type` = ?, `result_interval` = ?, `result_currency` = ? WHERE `chat_id` = ?");
			$stmt->execute([$result_type, $result_interval, $result_currency, $chat_id]);
		} elseif ($type == 'get') {
			$stmt = $pdo->prepare("SELECT `result_type`, `result_interval`, `result_currency` FROM `condition` WHERE `chat_id` = ?");
			$stmt->execute([$chat_id]);
		}  elseif ($type == 'update_currency') {
			$stmt = $pdo->prepare("UPDATE `condition` SET `result_currency` = ? WHERE `chat_id` = ?");
			$stmt->execute([$result_currency, $chat_id]);
		}

		$res = $stmt->fetchAll();

		return $type == 'get' ? $res : true;
	}

	public static function result($chat_id, $type, $interval, $currency)
	{
		$pdo = (new Db)->Db();

		$month = [1 => 'январь', 'февраль', 'март', 'апрель', 'май', 'июнь', 'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь'];

		$type_answer = match ($type) {
			'+' => 'Доходы',
			'-' => 'Расходы',
			default => 'В общем'
		};

		$interval_answer = match ($interval) {
			'day' => 'сегодня',
			'month' => $month[date('n')]
		};

		$answer = "<b>$type_answer за $interval_answer</b> в <b>$currency</b>\n" . PHP_EOL;

		if ($interval == 'day') {
			if ($type == '+' or $type == '-') {
				$stmt = $pdo->prepare("SELECT SUM(`amount`) AS `amount`, `currency`, `type`, `category` FROM `records` WHERE `chat_id` = ? AND `type` = ? AND DATE(`date&time`) = DATE(?) GROUP BY `category`, `currency`");
			} elseif ($type == NULL) {
				$stmt = $pdo->prepare("SELECT SUM(`amount`) AS `amount`, `currency`, `type` FROM `records` WHERE `chat_id` = ? AND DATE(`date&time`) = DATE(?) GROUP BY `type`, `currency`");
			}
		} elseif ($interval == 'month') {
			if ($type == '+' or $type == '-') {
				$stmt = $pdo->prepare("SELECT SUM(`amount`) AS `amount`, `currency`, `type`, `category` FROM `records` WHERE `chat_id` = ? AND `type` = ? AND DATE(`date&time`) BETWEEN DATE(?) AND DATE(?) GROUP BY `category`, `currency`");
			} elseif ($type == NULL) {
				$stmt = $pdo->prepare("SELECT SUM(`amount`) AS `amount`, `currency`, `type` FROM `records` WHERE `chat_id` = ? AND DATE(`date&time`) BETWEEN DATE(?) AND DATE(?) GROUP BY `type`, `currency`");
			}
		}

		if ($type != NULL) {
			if ($interval == 'day') {
				$stmt->execute([$chat_id, $type, date('Y-m-d')]);
			} elseif ($interval == 'month') {
				$stmt->execute([$chat_id, $type, date('Y-m-') . '01', date('Y-m-d')]);
			}
		} else {
			if ($interval == 'day') {
				$stmt->execute([$chat_id, date('Y-m-d')]);
			} elseif ($interval == 'month') {
				$stmt->execute([$chat_id, date('Y-m-') . '01', date('Y-m-d')]);
			}
		}

		if ($type == '+' or $type == '-') {
			foreach ($stmt->fetchAll() as $item)
				if ($item['currency'] == $currency) $answer .= "{$item['category']} - {$item['amount']} {$item['currency']}\n" . PHP_EOL;
		} elseif ($type == NULL) {
			foreach ($stmt->fetchAll() as $item)
				if ($item['currency'] == $currency) $item['type'] == '+' ? $plus = $item['amount'] : $minus = $item['amount'];

			$plus = $plus ?? 0;
			$minus = $minus ?? 0;

			$answer .= "Доходов: $plus, расходов: $minus\nИтог за $interval_answer: " . $plus - $minus;
		}

		return $answer;
	}

	public static function get_more_results($chat_id, $type, $interval, $currency)
	{
		$pdo = (new Db)->Db();

		$month = [1 => 'январь', 'февраль', 'март', 'апрель', 'май', 'июнь', 'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь'];

		$type_answer = match ($type) {
			'+' => 'Доходы',
			'-' => 'Расходы',
			default => 'В общем'
		};

		$interval_answer = match ($interval) {
			'day' => 'сегодня',
			'month' => $month[date('n')]
		};

		$answer = "Подробнее про \"$type_answer за $interval_answer\"\n\n";

		if ($type == '+' or $type == '-') {
			if ($interval == 'day') {
				$stmt = $pdo->prepare("SELECT `date&time`, `amount`, `category`, `description` FROM `records` WHERE `chat_id` = ? AND `type` = ? AND DATE(`date&time`) = DATE(?) AND `currency` = ?");
				$stmt->execute([$chat_id, $type, date('Y-m-d'), $currency]);
			} else {
				$stmt = $pdo->prepare("SELECT `date&time`, `amount`, `category`, `description` FROM `records` WHERE `chat_id` = ? AND `type` = ? AND DATE(`date&time`) BETWEEN DATE(?) AND DATE(?) AND `currency` = ?");
				$stmt->execute([$chat_id, $type, date('Y-m-') . '01', date('Y-m-d'), $currency]);
			}

			foreach ($stmt->fetchAll() as $item) {
				$answer .= $interval == 'day' ? substr($item['date&time'], 11, 5) . " = {$item['amount']} $currency - {$item['category']}. Описание: {$item['description']}\n\n" : str_replace('-', '.', substr($item['date&time'], 5, 11)) . " = {$item['amount']} $currency - {$item['category']}. Описание: {$item['description']}\n\n";
			}
		} else {
			if ($interval == 'day') {
				$stmt = $pdo->prepare("SELECT `date&time`, `type`, `amount`, `category`, `description` FROM `records` WHERE `chat_id` = ? AND DATE(`date&time`) = DATE(?) AND `currency` = ?");
				$stmt->execute([$chat_id, date('Y-m-d'), $currency]);
			} else {
				$stmt = $pdo->prepare("SELECT `date&time`, `type`, `amount`, `category`, `description` FROM `records` WHERE `chat_id` = ? AND DATE(`date&time`) BETWEEN DATE(?) AND DATE(?) AND `currency` = ?");
				$stmt->execute([$chat_id, date('Y-m-') . '01', date('Y-m-d'), $currency]);
			}

			foreach ($stmt->fetchAll() as $item) {
				$answer .= $interval == 'day' ? substr($item['date&time'], 11, 5) . " = {$item['type']}{$item['amount']} $currency - {$item['category']}. Описание: {$item['description']}\n\n" : str_replace('-', '.', substr($item['date&time'], 5, 11)) . " = {$item['type']}{$item['amount']} $currency - {$item['category']}. Описание: {$item['description']}\n\n";
			}
		}

		return $answer;
	}

	public static function answer_to_delete($chat_id, $type, $delete_interval = NULL)
	{
		$pdo = (new Db)->Db();

		if ($type == 'set') {
			$stmt = $pdo->prepare("UPDATE `condition` SET `delete_interval` = ? WHERE `chat_id` = ?");
			$stmt->execute([$delete_interval, $chat_id]);
		} elseif ($type == 'get') {
			$stmt = $pdo->prepare("SELECT `delete_interval` FROM `condition` WHERE `chat_id` = ?");
			$stmt->execute([$chat_id]);
		}

		$res = $stmt->fetchAll();

		return $type == 'get' ? $res[0] : true;
	}

	public static function delete($chat_id, $delete_interval)
	{
		$pdo = (new Db)->Db();

		if ($delete_interval == 'last') {
			$stmt = $pdo->prepare("DELETE FROM `records` WHERE `chat_id` = ? ORDER BY id DESC LIMIT 1");
			$stmt->execute([$chat_id]);
		} elseif ($delete_interval == 'month') {
			$stmt = $pdo->prepare("DELETE FROM `records` WHERE `chat_id` = ? AND DATE(`date&time`) BETWEEN DATE(?) AND DATE(?)");
			$stmt->execute([$chat_id, date('Y-m-') . '01', date('Y-m-d')]);
		} elseif ($delete_interval == 'all') {
			$stmt = $pdo->prepare("DELETE FROM `records` WHERE `chat_id` = ?");
			$stmt->execute([$chat_id]);
		}
	}

	public static function currency($chat_id, $type, $currency = NULL, $conversion = NULL, $first_name = '')
	{
		$pdo = (new Db)->Db();

		if ($type == 'set_currency') {
			$stmt = $pdo->prepare("UPDATE `currency` SET `currency` = ? WHERE `chat_id` = ?");
			$stmt->execute([$currency, $chat_id]);
		} elseif ($type == 'set_conversion') {
			$stmt = $pdo->prepare("UPDATE `currency` SET `conversion` = ? WHERE `chat_id` = ?");
			$stmt->execute([$conversion, $chat_id]);
		} elseif ($type == 'get') {
			$stmt = $pdo->prepare("SELECT `currency`, `conversion` FROM `currency` WHERE `chat_id` = ?");
			$stmt->execute([$chat_id]);
		} elseif ($type == 'create') {
			$stmt = $pdo->prepare("INSERT INTO `currency` (`chat_id`, `first_name`, `currency`, `conversion`) VALUES (?, ?, ?, ?)");
			$stmt->execute([$chat_id, $first_name, $currency, $conversion]);
		}

		$res = $stmt->fetchAll();

		return $type == 'get' ? $res[0] : true;
	}

	public static function conversion($chat_id, $currency, $type, $interval)
	{
		$pdo = (new Db)->Db();

		$stmt = $pdo->prepare("SELECT `USD`, `EUR` FROM `mono_currency` WHERE `id` = ?");
		$stmt->execute([1]);
		$rate = $stmt->fetchAll()[0];
		$USD = $rate['USD'];
		$EUR = $rate['EUR'];

		return $res;
	}
}