<?php

namespace Tools\Do;

use Tools\Prepare\Keyboards;
use Telegram\Bot\Keyboard\Keyboard;

class MakeKeyboard extends Keyboards
{
	public static function get_keyboard($keyboard)
	{
		$get_keyboard = Keyboard::make([
			'keyboard' => $keyboard,
			'resize_keyboard' => true,
			'one_time_keyboard' => false,
		]);
		return $get_keyboard;
	}

	public static function get_inline($inline)
	{
		$get_inline = Keyboard::make([
			'inline_keyboard' => $inline
		]);
		return $get_inline;
	}
}