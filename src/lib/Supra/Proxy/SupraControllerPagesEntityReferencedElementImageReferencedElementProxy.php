<?php

namespace Supra\Proxy;

/**
 * THIS CLASS WAS GENERATED BY THE DOCTRINE ORM. DO NOT EDIT THIS FILE.
 */
class SupraControllerPagesEntityReferencedElementImageReferencedElementProxy extends \Supra\Controller\Pages\Entity\ReferencedElement\ImageReferencedElement implements \Doctrine\ORM\Proxy\Proxy
{
    private $_entityPersister;
    private $_identifier;
    public $__isInitialized__ = false;
    public function __construct($entityPersister, $identifier)
    {
        $this->_entityPersister = $entityPersister;
        $this->_identifier = $identifier;
    }
    /** @private */
    public function __load()
    {
        if (!$this->__isInitialized__ && $this->_entityPersister) {
            $this->__isInitialized__ = true;

            if (method_exists($this, "__wakeup")) {
                // call this after __isInitialized__to avoid infinite recursion
                // but before loading to emulate what ClassMetadata::newInstance()
                // provides.
                $this->__wakeup();
            }

            if ($this->_entityPersister->load($this->_identifier, $this) === null) {
                throw new \Doctrine\ORM\EntityNotFoundException();
            }
            unset($this->_entityPersister, $this->_identifier);
        }
    }
    
    
    public function getImageId()
    {
        $this->__load();
        return parent::getImageId();
    }

    public function setImageId($imageId)
    {
        $this->__load();
        return parent::setImageId($imageId);
    }

    public function getAlign()
    {
        $this->__load();
        return parent::getAlign();
    }

    public function setAlign($align)
    {
        $this->__load();
        return parent::setAlign($align);
    }

    public function getStyle()
    {
        $this->__load();
        return parent::getStyle();
    }

    public function setStyle($style)
    {
        $this->__load();
        return parent::setStyle($style);
    }

    public function getSizeName()
    {
        $this->__load();
        return parent::getSizeName();
    }

    public function setSizeName($sizeName)
    {
        $this->__load();
        return parent::setSizeName($sizeName);
    }

    public function getWidth()
    {
        $this->__load();
        return parent::getWidth();
    }

    public function setWidth($width)
    {
        $this->__load();
        return parent::setWidth($width);
    }

    public function getHeight()
    {
        $this->__load();
        return parent::getHeight();
    }

    public function setHeight($height)
    {
        $this->__load();
        return parent::setHeight($height);
    }

    public function getAlternativeText()
    {
        $this->__load();
        return parent::getAlternativeText();
    }

    public function setAlternativeText($alternativeText)
    {
        $this->__load();
        return parent::setAlternativeText($alternativeText);
    }

    public function toArray()
    {
        $this->__load();
        return parent::toArray();
    }

    public function fillArray(array $array)
    {
        $this->__load();
        return parent::fillArray($array);
    }

    public function getDiscriminator()
    {
        $this->__load();
        return parent::getDiscriminator();
    }

    public function matchDiscriminator(\Supra\Controller\Pages\Entity\Abstraction\Entity $object, $strict = true)
    {
        $this->__load();
        return parent::matchDiscriminator($object, $strict);
    }

    public function getId()
    {
        $this->__load();
        return parent::getId();
    }

    public function equals(\Supra\Database\Entity $entity)
    {
        $this->__load();
        return parent::equals($entity);
    }

    public function __toString()
    {
        $this->__load();
        return parent::__toString();
    }

    public function getProperty($name)
    {
        $this->__load();
        return parent::getProperty($name);
    }


    public function __sleep()
    {
        return array('__isInitialized__', 'id', 'imageId', 'align', 'style', 'sizeName', 'width', 'height', 'alternativeText');
    }

    public function __clone()
    {
        if (!$this->__isInitialized__ && $this->_entityPersister) {
            $this->__isInitialized__ = true;
            $class = $this->_entityPersister->getClassMetadata();
            $original = $this->_entityPersister->load($this->_identifier);
            if ($original === null) {
                throw new \Doctrine\ORM\EntityNotFoundException();
            }
            foreach ($class->reflFields AS $field => $reflProperty) {
                $reflProperty->setValue($this, $reflProperty->getValue($original));
            }
            unset($this->_entityPersister, $this->_identifier);
        }
        
    }
}