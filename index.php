<?php
require_once __DIR__ . '/vendor/autoload.php';

use Telegram\Bot\Api;
use Tools\{Config};
use Tools\Prepare\{Keyboards};
use Tools\Do\{MakeKeyboard, Commands, DbQuery};

$telegram = new Api(Config::TOKEN);
$update = $telegram->getWebhookUpdate();

file_put_contents(__DIR__ . '/logs.txt', print_r($update, true), FILE_APPEND);

if (!isset($update['callback_query'])) {
	$text = $update['message']['text'];
	$chat_id = $update['message']['from']['id'];
	$message_id = $update['message']['message_id'];
	$first_name = $update['message']['chat']['first_name'];
} else {
	$callback_data = $update['callback_query']['data'];
	$chat_id = $update['callback_query']['message']['chat']['id'];
	$message_id = $update['callback_query']['message']['message_id'];
	$first_name = $update['callback_query']['message']['chat']['first_name'];
}

if (!isset($update['callback_query'])) {
	if (empty(DbQuery::condition($chat_id, 'get'))) {
		DbQuery::condition($chat_id, 'add', first_name: $first_name);
		DbQuery::currency($chat_id, 'create', 'UAH', 'UAH', $first_name);
	} elseif (DbQuery::condition($chat_id, 'get')['amount'] == 1) {
		if (is_numeric($text)) {
			$category = DbQuery::get_category($chat_id, 1);
			$currency = DbQuery::currency($chat_id, 'get')['currency'];
			DbQuery::add_record($chat_id, $first_name, DbQuery::get_type($category), $text, $currency, $category);
			$telegram->sendMessage([
				'chat_id' => $chat_id,
				'text' => 'Запись добавлена! Хотите добавить описание?',
				'reply_markup' => MakeKeyboard::get_inline(Keyboards::$description),
			]);
			DbQuery::condition($chat_id, 'set');
		} else {
			$telegram->sendMessage([
				'chat_id' => $chat_id,
				'text' => 'это не цифра попробуй ещё раз',
			]);
		}
		exit;
	} elseif (DbQuery::condition($chat_id, 'get')['description'] == 1) {
		DbQuery::add_description($chat_id, $text);
		$telegram->sendMessage([
			'chat_id' => $chat_id,
			'text' => 'Запись добавлена с описанием!',
			'reply_markup' => MakeKeyboard::get_keyboard(Keyboards::$static_keyboard),
		]);
		DbQuery::condition($chat_id, 'set');
		exit;
	}

	if ($text[0] == '/' or $text == '❓ Помощь') {
		$telegram->sendMessage([
			'chat_id' => $chat_id,
			'text' => Commands::Command($text),
			'parse_mode' => 'HTML',
			'reply_markup' => MakeKeyboard::get_keyboard(Keyboards::$static_keyboard),
		]);
	} elseif ($text == '🖊 Добавить') {
		$telegram->sendMessage([
			'chat_id' => $chat_id,
			'text' => '🖊 Добавить',
			'reply_markup' => MakeKeyboard::get_inline(Keyboards::$choose_input_type),
		]);
	} elseif ($text == '⚙️ Настройки') {
		$currency = DbQuery::currency($chat_id, 'get')['currency'];
		$conversion = DbQuery::currency($chat_id, 'get')['conversion'];

		$settings = [
			[
				['text' => "($currency) Валюта ввода", 'callback_data' => 'change_currency'],
			], [
				['text' => "($conversion) Валюта конвертации", 'callback_data' => 'change_conversion'],
			]
		];

		$telegram->sendMessage([
			'chat_id' => $chat_id,
			'text' => '⚙️ Настройки',
			'reply_markup' => MakeKeyboard::get_inline($settings),
		]);
	} elseif ($text == '📊 Итоги') {
		$telegram->sendMessage([
			'chat_id' => $chat_id,
			'text' => '📊 Итоги',
			'reply_markup' => MakeKeyboard::get_inline(Keyboards::$preresults),
		]);
	} elseif ($text == '🔧 Управление') {
		$telegram->sendMessage([
			'chat_id' => $chat_id,
			'text' => '🔧 Управление',
			'reply_markup' => MakeKeyboard::get_inline(Keyboards::$manage_records),
		]);
	} else {
		$telegram->sendMessage([
			'chat_id' => $chat_id,
			'text' => 'Я этого не понимаю :(',
			'reply_markup' => MakeKeyboard::get_keyboard(Keyboards::$static_keyboard),
		]);
	}
} elseif (isset($update['callback_query'])) {
	if ($callback_data == '+' or $callback_data == '-') {
		$telegram->editMessageText([
			'chat_id' => $chat_id,
			'message_id' => $message_id,
			'text' => $callback_data == '+' ? '🔍 Выберите категорию дохода' : '🔎 Выберите категорию расхода',
			'reply_markup' => MakeKeyboard::get_inline(Keyboards::categories($callback_data == '+' ? '+' : '-')),
		]);
	} elseif ($callback_data == 'choose_input_type' or $callback_data == 'choose_input_type_back') {
		if ($callback_data == 'choose_input_type_back') {
			DbQuery::condition($chat_id, 'set');
		}
		$telegram->editMessageText([
			'chat_id' => $chat_id,
			'message_id' => $message_id,
			'text' => '🖊 Добавить',
			'reply_markup' => MakeKeyboard::get_inline(Keyboards::$choose_input_type),
		]);
	} elseif (is_numeric($callback_data)) {
		DbQuery::condition($chat_id, 'set', NULL, $callback_data, 1);
		input:
		$type = DbQuery::condition($chat_id, 'get');
		$category = DbQuery::get_category($type['category']);

		if ($type['type'] == '+') {
			$word = 'дохода';
		} else {
			$word = 'расхода';
		}

		$currency = DbQuery::currency($chat_id, 'get')['currency'];
		$currency_type = match ($currency) {
			'UAH' => 'гривнах ',
			'USD' => 'долларах',
			'EUR' => 'евро'
		};

		$res = match ($currency) {
			'UAH' => 'USD',
			'USD' => 'EUR',
			'EUR' => 'UAH'
		};

		$keyboard = [
			['text' => '❌ Отмена', 'callback_data' => 'choose_input_type_back'],
		];

		array_splice($keyboard, 0, 0, [
			['text' => "🔁 на $res", 'callback_data' => 'another_currency'],
		]);

		$telegram->editMessageText([
			'chat_id' => $chat_id,
			'message_id' => $message_id,
			'text' => "Отправьте мне сумму для $word <b>$category</b> в <b>$currency_type</b>",
			'reply_markup' => MakeKeyboard::get_inline(array_chunk($keyboard, 2)),
			'parse_mode' => 'HTML'
		]);
	} elseif ($callback_data == 'another_currency') {
		$res = DbQuery::currency($chat_id, 'get')['currency'];
		$currency = match ($res) {
			'UAH' => 'USD',
			'USD' => 'EUR',
			'EUR' => 'UAH'
		};
		DbQuery::currency($chat_id, 'set_currency', $currency);
		goto input;
	} elseif ($callback_data == 'add_description') {
		DbQuery::condition($chat_id, 'set', 1);
		$telegram->editMessageText([
			'chat_id' => $chat_id,
			'message_id' => $message_id,
			'text' => 'Введите мне описание',
			'reply_markup' => MakeKeyboard::get_inline(Keyboards::$back),
		]);
	} elseif ($callback_data == 'no_description') {
		$telegram->deleteMessage(['chat_id' => $chat_id, 'message_id' => $message_id]);
		$telegram->sendMessage([
			'chat_id' => $chat_id,
			'text' => 'Запись добавлена без описания!',
			'reply_markup' => MakeKeyboard::get_keyboard(Keyboards::$static_keyboard),
		]);
	} elseif ($callback_data == 'results') {
		DbQuery::more_results($chat_id, 'set');
		$telegram->editMessageText([
			'chat_id' => $chat_id,
			'message_id' => $message_id,
			'text' => '📊 Итоги',
			'reply_markup' => MakeKeyboard::get_inline(Keyboards::$preresults),
		]);
	} elseif ($callback_data == 'results_today' or $callback_data == 'results_month') {
		$telegram->editMessageText([
			'chat_id' => $chat_id,
			'message_id' => $message_id,
			'text' => $callback_data == 'results_today' ? '⌚ За сегодня' : '🗓️ За месяц',
			'reply_markup' => MakeKeyboard::get_inline($callback_data == 'results_today' ? Keyboards::$results_today : Keyboards::$results_month),
		]);
	} elseif (stristr($callback_data, 'day') or stristr($callback_data, 'month')) {
		$type = NULL;
		$interval = $callback_data;
		if ($callback_data[0] == '+' or $callback_data[0] == '-') {
			$type = $callback_data[0];
			$interval = substr($callback_data, 1);
		}
		$conversion = DbQuery::currency($chat_id, 'get')['conversion'];
		Keyboards::$results['UAH'][] = [
			['text' => '⬅️ Назад', 'callback_data' => 'results'],
			['text' => "💱 Всё в $conversion", 'callback_data' => 'conversion'],
		];
		DbQuery::more_results($chat_id, 'set', 'UAH', $type, $interval);
		$resoult = DbQuery::result($chat_id, $type, $interval, 'UAH');
		$telegram->editMessageText([
			'chat_id' => $chat_id,
			'message_id' => $message_id,
			'text' => $resoult,
			'parse_mode' => 'HTML',
			'reply_markup' => MakeKeyboard::get_inline(Keyboards::$results['UAH']),
		]);
	} elseif ($callback_data == 'more_results') {
		$res = DbQuery::more_results($chat_id, 'get')[0];

		$get_more_results = DbQuery::get_more_results($chat_id, $res['result_type'], $res['result_interval'], $res['result_currency']);

		DbQuery::more_results($chat_id, 'set');

		$telegram->editMessageText([
			'chat_id' => $chat_id,
			'message_id' => $message_id,
			'text' => $get_more_results,
			'parse_mode' => 'HTML',
		]);
	} elseif ($callback_data == 'results_UAH' or $callback_data == 'results_USD' or $callback_data == 'results_EUR') {
		$currency = match ($callback_data) {
			'results_UAH' => 'UAH',
			'results_USD' => 'USD',
			'results_EUR' => 'EUR',
		};

		DbQuery::more_results($chat_id, 'update_currency', $currency);

		$get = DbQuery::more_results($chat_id, 'get')[0];
		$conversion = DbQuery::currency($chat_id, 'get')['conversion'];

		$result = DbQuery::result($chat_id, $get['result_type'], $get['result_interval'], $currency);

		Keyboards::$results[$currency][] = [
			['text' => '⬅️ Назад', 'callback_data' => 'results'],
			['text' => "💱 Всё в $conversion", 'callback_data' => 'conversion'],
		];

		$telegram->editMessageText([
			'chat_id' => $chat_id,
			'message_id' => $message_id,
			'text' => $result,
			'parse_mode' => 'HTML',
			'reply_markup' => MakeKeyboard::get_inline(Keyboards::$results[$currency]),
		]);
	} elseif ($callback_data == 'delete_l' or $callback_data == 'delete_m' or $callback_data == 'delete_all') {

		$interval = match ($callback_data) {
			'delete_l' => 'last',
			'delete_m' => 'month',
			'delete_all' => 'all'
		};

		DbQuery::answer_to_delete($chat_id, 'set', $interval);

		$delete_interval = match ($interval) {
			'last' => 'последнюю запись',
			'month' => 'записи за месяц',
			'all' => 'все свои записи'
		};

		$telegram->editMessageText([
			'chat_id' => $chat_id,
			'message_id' => $message_id,
			'text' => "<b>Вы точно хотите удалить $delete_interval?</b>",
			'parse_mode' => 'HTML',
			'reply_markup' => MakeKeyboard::get_inline(Keyboards::$delete_records),
		]);
	} elseif ($callback_data == 'not_delete') {
		DbQuery::answer_to_delete($chat_id, 'set');
		$telegram->editMessageText([
			'chat_id' => $chat_id,
			'message_id' => $message_id,
			'text' => '🔧 Управление',
			'reply_markup' => MakeKeyboard::get_inline(Keyboards::$manage_records),
		]);
	} elseif ($callback_data == 'delete') {

		$interval = DbQuery::answer_to_delete($chat_id, 'get')['delete_interval'];
		DbQuery::delete($chat_id, $interval);
		DbQuery::answer_to_delete($chat_id, 'set');

		$delete_interval = match ($interval) {
			'last' => '🧨 Последняя запись удалена!',
			'month' => '💣 Записи за месяц удалены!',
			'all' => '❌ Все ваши записи удалены!'
		};

		$telegram->editMessageText([
			'chat_id' => $chat_id,
			'message_id' => $message_id,
			'text' => $delete_interval,
			'parse_mode' => 'HTML',
		]);
//	} elseif ($callback_data == 'plan_budget') {
//		try {
//			$telegram->editMessageText([
//				'chat_id' => $chat_id,
//				'message_id' => $message_id,
//				'text' => '🎯 Планирование (не доделано)',
//			]);
//		} catch (Exception $error) {
//			$telegram->editMessageText([
//				'chat_id' => $chat_id,
//				'message_id' => $message_id,
//				'text' => "У тебя ошибка!\n\n" . $error->getMessage() . "\n\n" . $error,
//			]);
//		}
	} elseif ($callback_data == 'change_currency') {
		change_currency:
		$currency = DbQuery::currency($chat_id, 'get')['currency'];

		$change_currency = [];
		$currency_type = ['UAH', 'USD', 'EUR'];

		foreach ($currency_type as $item) {
			$change_currency[] = $item == $currency ? ['text' => "✅ $item", 'callback_data' => $item] : ['text' => $item, 'callback_data' => $item];
		}

		$change_currency[] = ['text' => '⬅️ Назад', 'callback_data' => 'settings'];

		$telegram->editMessageText([
			'chat_id' => $chat_id,
			'message_id' => $message_id,
			'text' => "($currency) Валюта ввода",
			'reply_markup' => MakeKeyboard::get_inline(array_chunk($change_currency, 1)),
		]);
	} elseif ($callback_data == 'settings') {
		$currency = DbQuery::currency($chat_id, 'get')['currency'];
		$conversion = DbQuery::currency($chat_id, 'get')['conversion'];

		$settings = [
			[
				['text' => "($currency) Валюта ввода", 'callback_data' => 'change_currency'],
			], [
				['text' => "($conversion) Валюта конвертации", 'callback_data' => 'change_conversion'],
			]
		];

		$telegram->editMessageText([
			'chat_id' => $chat_id,
			'message_id' => $message_id,
			'text' => '⚙️ Настройки',
			'reply_markup' => MakeKeyboard::get_inline($settings),
		]);
	} elseif ($callback_data == 'UAH' or $callback_data == 'USD' or $callback_data == 'EUR') {
		DbQuery::currency($chat_id, 'set_currency', $callback_data);

		goto change_currency;
	} elseif ($callback_data == 'change_conversion') {
		change_conversion:
		$conversion = DbQuery::currency($chat_id, 'get')['conversion'];

		$change_conversion = [];
		$conversion_type = ['UAH', 'USD', 'EUR'];

		foreach ($conversion_type as $item) {
			$change_conversion[] = $item == $conversion ? ['text' => "✅ $item", 'callback_data' => "conversion_$item"] : ['text' => $item, 'callback_data' => "conversion_$item"];
		}
		$change_conversion[] = ['text' => '⬅️ Назад', 'callback_data' => 'settings'];

		$telegram->editMessageText([
			'chat_id' => $chat_id,
			'message_id' => $message_id,
			'text' => "($conversion) Валюта конвертации",
			'reply_markup' => MakeKeyboard::get_inline(array_chunk($change_conversion, 1)),
		]);
	} elseif ($callback_data == 'conversion_UAH' or $callback_data == 'conversion_USD' or $callback_data == 'conversion_EUR') {
		$conversion = match ($callback_data) {
			'conversion_UAH' => 'UAH',
			'conversion_USD' => 'USD',
			'conversion_EUR' => 'EUR'
		};
		DbQuery::currency($chat_id, 'set_conversion', conversion: $conversion);
		goto change_conversion;
	} elseif ($callback_data == 'conversion') {
//		$conversion = DbQuery::currency($chat_id, 'get')['conversion'];
//		$type = DbQuery::more_results($chat_id, 'get')[0];
//		$res = DbQuery::conversion($chat_id, $conversion, $type['result_type'], $type['result_interval']);

		$telegram->editMessageText([
			'chat_id' => $chat_id,
			'message_id' => $message_id,
			'text' =>  'делаеться',
		]);
	}
}