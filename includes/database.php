<?php

class NSEvent_Database
{
	private $pdo, $prefix = '';
	
	public function __construct(array $settings)
	{
		#
		# Establish connection via PDO
		#
		try
		{
			$this->pdo = new PDO(
				sprintf('%s:host=%s;%sdbname=%s',
					'mysql',
					$settings['host'],
					empty($settings['port']) ? '' : sprintf('port={%d};', $settings['port']),
					$settings['name']),
				$settings['user'],
				$settings['password'],
				array(
					PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
					PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC));
			
			$this->pdo->query('SET NAMES "utf8";');
			
			if (!empty($settings['prefix']))
				$this->prefix = $settings['prefix'];
		}
		catch (PDOException $e)
		{
			$message = preg_replace('/[A-Z]+\[[0-9]+\]: .+ [0-9]+ (.*?)/', '$1', $e->getMessage());
			throw new Exception($message);
		}
	}

	#
	# Function: query
	# Executes a SQL query.
	#
	public function query($query, array $params = array(), $use_prefix = True)
	{
		try
		{
			if ($use_prefix)
				$query = sprintf($query, $this->prefix);
			
			$statement = $this->pdo->prepare($query);
			
			if (!($statement->execute($params)))
				throw PDOException();
		}
		catch (PDOException $e)
		{
			$message = preg_replace("/[A-Z]+\[[0-9]+\]: .+ [0-9]+ (.*?)/", "\\1", $e->getMessage());
			
			if (defined('WP_DEBUG'))
				$message .= "</p>\n\n<pre>$query\n\n".print_r($params, True)."</pre>\n\n";
			
			throw new Exception($message);
		}
		
		return $statement;
	}

	#
	# Function: lastInsertID
	# Returns the ID of the last inserted row.
	#
	public function lastInsertID($name = '')
	{
		return $this->pdo->lastInsertID($name);
	}

	#
	# Function: quote
	# Quotes a string for use in a query.
	#
	public function quote($string)
	{
		return $this->pdo->quote($string);
	}
}
