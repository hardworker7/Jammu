<?php

namespace Jammu\core;

include __DIR__ . '/../autoload.php';

use Jammu\configs\Configs;

/**
* Jammu-instance class that provides actions for jammu running
*/
class JammuI
{
	/**
	*	sendMessage
	*	@param StdClass object {address, body}
	*/
	public static function sendMessage($tosend)
	{
		// converting message into StdClass
		$message = is_array($tosend) ? (object) $tosend : $tosend;
		// getting pending message
		$js = json_decode(file_get_contents(__DIR__ . '/../gateways/tosend.json'), true);
		// adding message to send: there could have many
		if (is_array($message->address))
		{
			$js['waiting'][] = (object) ["address" => $message->address, "body" => $message->body];
			/*foreach ($message->address as $k => $v) {
				$js['waiting'][] = (object) ["address" => trim($v), "body" => $message->body];
			}*/
		}
		else if (preg_match("#[,]+#", $message->address))
		{
			$message->address = explode(',', $message->address);
			foreach ($message->address as $k => $v)
			{
				$message->address[$k] = trim($v);
				/*$js['waiting'][] = (object) ["address" => trim($v), "body" => $message->body];*/
			}
			$js['waiting'][] = (object) ["address" => $message->address, "body" => $message->body];
		}
		else {
			$js['waiting'][] = $message;
		}
		// save
		if (!file_put_contents(__DIR__ . '/../gateways/tosend.json', json_encode($js)))
		{
			throw new Exception("Erreur JAMMU 101", 1);
		}
	}

	public static function messageExists($list, $message)
	{
		$ison = false;
		foreach ($list as $k => $msg) {
			if ($msg['date_sent'] == $message['date_sent'] && $msg['address'] == $message['address'] && $msg['body'] == $message['body']) {
				$ison = true;
			}
		}
		return $ison;
	}

	/**
	*	app
	*	@param String appname
	*	@param Std::class object {address, body}
	*/
	public static function app($appname, $message, $pass=true)
	{
		if ($pass)
		{
			if (is_dir(__DIR__ . '/../apps/' . $appname) && file_exists(__DIR__ . '/../apps/' . $appname . '/app.php'))
			{
				// integrating project app.php file
				require(__DIR__ . '/../apps/' . $appname . '/app.php');

				// try calling the entry method
				if (method_exists($appname, 'call'))
				{
					$appname::call($message);
				}
				else {
					throw new \Exception("You have to define call method in app.php file.", 1);
				}
			}
			else {
				throw new \Exception("Can't find $appname project directory or app.php file.", 1);
			}
		}
	}

	/**
	*	say
	*	@param String text
	*	@return Void
	*/
	public static function say($text, $brk=true)
	{
		echo $text.($brk ? "\n" : '');
	}

	/**
	*	exec
	*	@param String command
	*	@return String command result
	*/
	public static function exec($command, $stop=false)
	{
		$command = trim($command);
		return exec($command.($stop ? " &" : ''));
	}

	/**
	*	save
	*	@param StdClass message
	*	@return Bool TRUE / FALSE
	*/
	public static function save($message)
	{
		try
		{
			$bd = self::getDbInstance();

			$q = $bd->prepare("INSERT INTO messages(address, body, send_date) VALUES(:address, :body, :send_date)");
			$q->execute([
				"address" => $message->address,
				"body" => $message->body,
				"send_date" => date("d/m/Y H:i")
			]);

			$q->closeCursor();
		}
		catch(Exception $e){
			die('Erreur : '.$e->getMessage()) ;
		}
	}

	/**
	 *	getDbInstance
	 *	@return PDO || FALSE
	 */
	public static function getDbInstance($dbname=false, $dbuser=false, $dbpass=false, $dbhost=false)
	{
		try
		{
			$configs = self::loadConfigs();
			$pdo_options[\PDO::ATTR_ERRMODE] = \PDO::ERRMODE_EXCEPTION;
			$db = new \PDO('mysql:dbname='. ($dbname ? $dbname : $configs->database) .'; host='.($dbhost ? $dbhost : $configs->hostname), ($dbuser ? $dbuser : $configs->username), ($dbpass ? $dbpass : $configs->password), $pdo_options);
			$db->exec("set names ".$configs->dbcharset);

			return $db;
		}
		catch (\Exception $e)
		{
			return false;
		}
	}

	/**
	 *	cleanMessages
	 *	@param String cached: where would you like to remove messages ? database or file ?
	 *	@return Bool TRUE / FALSE
	 */
	public static function cleanMessages($cached='file')
	{
		switch ($cached)
		{
			case 'file':
				return file_put_contents(__DIR__ . '/../gateways/messages.json', '[]');
				break;

			case 'db':
				try
				{
					$db = self::getDbInstance();
					$db->exec('DELETE FROM messages');
				}
				catch (Exception $e)
				{
					die("An error occured !");
				}
				break;
		}
	}

	public static function loadConfigs()
	{
		return Configs::getConfVars();
	}

	/**
	*	toServer
	*	@param String url
	*	@param Array values
	*	@param String type
	*	@return String server answer
	*/
	public static function toServer($url, $values=[], $type='get', $useragent=false)
	{
		$q = curl_init();

		if ($useragent) { curl_setopt($q, CURLOPT_USERAGENT, $useragent); }

		$url .= (strtolower($type) == "get") ? '?'.http_build_query($values) : '';

		curl_setopt($q, CURLOPT_URL, $url);
		curl_setopt($q, CURLOPT_SSL_VERIFYPEER, false);

		if (strtolower($type) == "post") {
			curl_setopt($q, CURLOPT_POST, 1);
			curl_setopt($q, CURLOPT_POSTFIELDS, http_build_query($values));
		}

		curl_setopt($q, CURLOPT_RETURNTRANSFER, 1);

		$response = curl_exec($q);

		curl_close($q);

		return $response;
	}

	/**
	*	log
	*	@param String message
	*/
	public static function log($message)
	{
		$filename 	= date('d.m.Y') . '.jlog';
		$old		= @file_get_contents(__DIR__ . '/../logs/' . $filename);

		file_put_contents(__DIR__ . '/../logs/' . $filename, $old . "\n" . $message);
	}
}
