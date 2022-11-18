<?php

namespace Tools\Prepare;

use Tools\Do\DbQuery;

class Keyboards
{
	public static array $static_keyboard = [
		['🖊 Добавить', '🔧 Управление'],
		['📊 Итоги'],
		['❓ Помощь', '⚙️ Настройки']
	];

	public static array $choose_input_type = [
		[
			['text' => '📈 Доход', 'callback_data' => '+'],
			['text' => '📉 Расход', 'callback_data' => '-']
		],
	];

	public static array $manage_records = [
		[
			['text' => '🧨 Удалить последнюю запись', 'callback_data' => 'delete_l'],
		], [
			['text' => '💣 Удалить записи за месяц', 'callback_data' => 'delete_m'],
		], [
			['text' => '❌ Удалить все мои записи', 'callback_data' => 'delete_all'],
		]
	];

	public static array $delete_records = [
		[
			['text' => '✅ Удалить', 'callback_data' => 'delete'],
			['text' => '❌ Не удалять', 'callback_data' => 'not_delete'],
		]
	];

	public static array $preresults = [
		[
			['text' => '⌚ За сегодня', 'callback_data' => 'results_today'],
		], [
			['text' => '🗓️ За месяц', 'callback_data' => 'results_month'],
		]
	];

	public static array $back = [
		[
			['text' => '❌ Отмена', 'callback_data' => 'choose_input_type_back'],
		]
	];

	public static array $description = [
		[
			['text' => '✅ Добавить', 'callback_data' => 'add_description'],
			['text' => '❌ Не нужно', 'callback_data' => 'no_description'],
		]
	];

	public static array $results_today = [
		[
			['text' => '📈 Доходы', 'callback_data' => '+day'],
			['text' => '📉 Расходы', 'callback_data' => '-day'],
		],
		[
			['text' => '⬅️ Назад', 'callback_data' => 'results'],
			['text' => '🔮 В общем', 'callback_data' => 'day'],
		]
	];

	public static array $results_month = [
		[
			['text' => '📈 Доходы', 'callback_data' => '+month'],
			['text' => '📉 Расходы', 'callback_data' => '-month'],
		],
		[
			['text' => '⬅️ Назад', 'callback_data' => 'results'],
			['text' => '🔮 В общем', 'callback_data' => 'month'],
		]
	];

	public static array $results = [
		'UAH' => [
			[
				['text' => '⬅️ EUR', 'callback_data' => 'results_EUR'],
				['text' => '🧐 Подробнее', 'callback_data' => 'more_results'],
				['text' => 'USD ➡️', 'callback_data' => 'results_USD'],
			],
		],

		'USD' => [
			[
				['text' => '⬅️ UAH', 'callback_data' => 'results_UAH'],
				['text' => '🧐 Подробнее', 'callback_data' => 'more_results'],
				['text' => 'EUR ➡️', 'callback_data' => 'results_EUR'],
			],
		],

		'EUR' => [
			[
				['text' => '⬅️ USD', 'callback_data' => 'results_USD'],
				['text' => '🧐 Подробнее', 'callback_data' => 'more_results'],
				['text' => 'UAH ➡️', 'callback_data' => 'results_UAH'],
			],
		],
	];

	public static function categories($type)
	{
		$arr = DbQuery::get_categories($type);
		$inline = array_chunk($arr, 2);

		return $inline;
	}
}