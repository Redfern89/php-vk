<?php
	/*
		Скрипт для отсылки сообщений в автоматическом режиме
		
		@author: Redfern89
		@date: 11.12.2023 9:50
		@packages php, php-curl, cron
		
		Для получения access_token, нужно зайти в настройки своего паблика -> Работа с API -> Кнопка "Создать ключ"
		После получения ключа - копируем его и вставляем в соответствующее поле. Далее нужно в настройках перейти в 
		Сообщения -> Настройки для бота -> Флажок "Разрешать добавлять сообщество в чаты". На главной странице паблика
		появится кнопка "добавить в чат". Добавляем, делаем бота админом! Это важно! Иначе не получиться найти peer_id
		
		После проделанных манипуляций помещаем скрипт в какой-нибудь каталог (например /home/$USER/vk.php), пробуем запустить
		$ php vk.php list_peers
		
		При успешном запуске получим примерно такой вывод 
		
		Propbing id: 2000000000
		Propbing id: 2000000001
		Propbing id: 2000000002
		........
		Propbing id: 2000000020


		-----------------------------------------------------
		Name: ТЕстовая беседа, peer_id: 2000000001
		Name: Отряд Флексики, peer_id: 2000000004
		Name: тестовый чат 💩, peer_id: 2000000007
		-----------------------------------------------------
		
		Интересующий нас peer_id копируем в соответствующее поле. Далее, выполняем
		$ crontab -e
		
		добавляем туда строку в конец ($USER меняем на имя нашего пользователя в linux):
		* * * * * /home/$USER/vk.php send_message
		
		Скрипт готов к работе
		
	*/
	header ('Content-Type: text/plain');
	
	define ('ACCESS_TOKEN', '');		// access_token, который ты должен получить в настройках своего пблика
	define ('PEER_PROBE_START', 0);		// Число, от которого будет идти отсчет для поиска чатов
	define ('PEER_PROBE_END', 20);		// Число, до которого будет идти отсчет для поиска чатов
	define ('PEER_ID', '');			// Сюда нужно поместить ID, который ты получишь из списка с запуском list_peers

	// Принимаем аргументы из консоли
	$argv = $_SERVER['argv'];
	// Текущая дата
	$current_date = date('H:i');

	/*
		Массив с датами и сообщениями, заполняется в формате время - сообщение
		
		
		'10:01' => 'Привет, уже 10:00, шлю сообщение',
		'10:02' => 'Бля, уже 10:02',
		'10:05' => 'Пидрила! Уже 10:05!!!!111!! 💩'
		......
		'21:00' => 'Уже 21:00'
	*/
	$messages_array = array(
	
	);
	
	// Функция для работы с VK API
	function _vkApi_Call($method, $params = []) {
		$params['v'] = '5.131'; // Версия 5.131
		$params['access_token'] = ACCESS_TOKEN; // Передача access_token
		$url = sprintf('https://api.vk.com/method/%s', $method); // URL для отсылки данных на сервер ВК

		$ch = curl_init(); // Инициализация
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Говорим, что-бы вывод curl был в переменную
		curl_setopt($ch, CURLOPT_URL, $url); // Указываем текущий URL
		$params = http_build_query($params); // Собираем из массива строку запросов
		curl_setopt($ch, CURLOPT_POST, true); // Говорим, что нам нужно отослать POST-запрос на сервер
		curl_setopt($ch, CURLOPT_POSTFIELDS, $params); // Указываем данные POST-запроса
		$data = curl_exec($ch); // Выполняем запрос
		curl_close($ch); // Закрываем cUrl
		
		return $data; // возвращаем ответ от сервера
	}
	
	// Функция генерации случайного числа от 0 до 4294967295
	function random_uint32_t() {
		// Знаю, по-идиотски, но умнее лень было придумывать
		return (int)sprintf('%010d', mt_rand(0, 4294967295));
	}
	
	// Функция отправки сообщения
	function _vkApi_sendMessage($peer_id, $text) {
		$vk = _vkApi_Call('messages.send', array(
			'peer_id' => $peer_id,
			'random_id' => random_uint32_t(),
			'message' => $text
		));
		
		return $vk;
	}

	// Смотрим, какие аргументы передаются их консоли
	if (isset($argv[1])) { // Если есть аргумент с индексом 1, то ...
		$act_arg = $argv[1];
		
		// Если аргумент нам говорит, что нужно отправить сообщение - ...
		if ($act_arg == 'send_message') {
			if (!empty($messages_array)) { // Если массив $messages_array не пуст - ...
				foreach ($messages_array as $date => $message) { // Перебираем его
					if ($date == $current_date) { // Если нашлась дата и она совпала с текущей, ...
						_vkApi_sendMessage(PEER_ID, $message); // Отсылаем сообщение из массива
					}
				}
			}
		}

		// Если мы хотим посмотреть список пиров, ...
		if ($act_arg == 'list_peers') {
			$peers = array();
			echo "\n";
			
			// Пробегаемя от PEER_PROBE_START до PEER_PROBE_END
			for ($i = PEER_PROBE_START; $i <= PEER_PROBE_END; $i++) {
				$peer_id = 2000000000 + $i; // peer_id в фомате 2000000000 + текущий ID
				
				echo sprintf("Probing id: %d\n", $peer_id);
				
				// Делаем проверяющий запрос на сервер
				$vk = _vkApi_Call('messages.getConversationsById', array('peer_ids' => $peer_id));
				$vk = json_decode($vk);
				
				// Если в ответе нашлось то, что нам нужно - ...
				if (isset($vk -> response -> items[0] -> peer)) { // Добавляем ответ в массив
					$item = $vk -> response -> items[0]; 
					$peers[] = sprintf("Name: %s, peer_id: %d", $item -> chat_settings -> title, $item -> peer -> id);
				}
			}
			echo "\n\n-----------------------------------------------------\n";
			echo implode("\n", $peers); // Выводим в консоль ответ
			echo "\n-----------------------------------------------------\n\n";
		}
	}

?>
