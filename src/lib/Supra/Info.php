<?php

namespace Supra;

use Supra\Configuration\Exception\ConfigurationMissing;
use Supra\Configuration\Exception\InvalidConfiguration;
use Supra\ObjectRepository\ObjectRepository;

class Info implements Configuration\ConfigurationInterface
{
	const NO_SCHEME = 1;
	const WITH_SCHEME = 2;

	/**
	 * Site ID for remote comunication
	 * @var string
	 */
	public $id;

	/**
	 * Project name
	 * @var string
	 */
	public $name = 'SiteSupra';

	/**
	 * Project version
	 * @var string 
	 */
	public $version = '7.0.0';

	/**
	 * Hostname
	 * @var string
	 */
	public $hostName;

	public function configure()
	{
		// fetching data from supra.ini
		$conf = ObjectRepository::getIniConfigurationLoader('');
		$this->id = $conf->getValue('system', 'id');
		$this->hostName = $conf->getValue('system', 'host');
		$this->name = $conf->getValue('system', 'name');

		$version = '@build.number@';

		$versionPath = dirname(SUPRA_PATH) . DIRECTORY_SEPARATOR . 'VERSION';
		if (file_exists($versionPath)) {
			$versionNumber = trim(file_get_contents($versionPath));
			if ( ! empty($versionNumber)) {
				$version = $versionNumber;
			}
		}

		$this->version = $version;

		ObjectRepository::setDefaultSystemInfo($this);
	}

	/**
	 * @param int $format
	 * @return string
	 */
	public function getHostName($format = self::NO_SCHEME)
	{
		if (empty($this->hostName)) {
			throw new ConfigurationMissing("Host name not configured for the system");
		}

		$hostname = $this->hostName;

		$url = parse_url($hostname);

		if ( ! isset($url['scheme'])) {
			$hostname = 'http://' . $hostname;
		}

		$url = parse_url($hostname);

		$string = null;

		if (isset($url['host'])) {
			// Glue the URL
			$string = (isset($url['user']) ? $url['user'] . (isset($url['pass']) ? ':' . $url['pass'] : '') . '@' : '')
					. $url['host']
					. (isset($url['port']) ? ':' . $url['host'] : '');
		} else {
			throw new InvalidConfiguration("Invalid system hostname value");
		}

		if ($format == self::WITH_SCHEME) {
			$string = $url['scheme'] . '://' . $string;
		}

		return $string;
	}

	public function getSystemId()
	{
		return $this->name . '_' . $this->version;
	}

	/**
	 * Returns remote site user id 
	 * @return string
	 */
	public function getSiteId()
	{
		return $this->id;
	}

}