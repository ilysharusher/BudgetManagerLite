<?php

namespace Tools\Do;

class Commands
{
	public static function Command($text): string
	{
		$text = match ($text) {
			'/start' => 'Привет! Я тебе помогу разобраться с финансами и сэкономить их в дальнейшем :)
Остались вопросы? Тыкни /help, или нажми соответствующую кнопку.',
			'/help', '❓ Помощь' => "Для введения своего дохода/расхода просто тыкни кнопку <b>Добавить запись</b>

Остались вопросы? Напиши в поддержку: @ilysharusherrr",
			default => 'Я этой команды не знаю :(',
		};
		return $text;
	}
}