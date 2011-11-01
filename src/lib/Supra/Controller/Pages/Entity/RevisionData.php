<?php

namespace Supra\Controller\Pages\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Supra\Controller\Pages\Exception;
use Supra\Database\Doctrine\Listener\Timestampable;
use DateTime;

/**
 * Revision data class
 * @Entity
 */
class RevisionData extends Abstraction\Entity implements Timestampable
{
	/**
	 * @Column(type="datetime", nullable=true, name="created_at")
	 * @var DateTime
	 */
	protected $creationTime;
	
	/**
	 * @Column(type="sha1")
	 * @var string
	 */
	protected $user;
	
	/**
	 * Returns revision author
	 * @return string
	 */
	public function getUser()
	{
		return $this->user;
	}
	
	/**
	 * Sets revision author
	 * @param string $user 
	 */
	public function setUser($user)
	{
		$this->user = $user;
	}
	
	/**
	 * Returns creation time
	 * @return DateTime
	 */
	public function getCreationTime()
	{
		return $this->creationTime;
	}
	
	/**
	 * Sets creation time
	 * @param DateTime $time
	 */
	public function setCreationTime(DateTime $time = null)
	{
		if (is_null($time)) {
			$time = new DateTime('now');
		}
		$this->creationTime = $time;
	}
	
	/**
	 * Doesn't store modification time
	 * @return DateTime
	 */
	public function getModificationTime()
	{
		return null;
	}

	/**
	 * Doesn't store modification time
	 * @param DateTime $time
	 */
	public function setModificationTime(DateTime $time = null)
	{
		
	}
}