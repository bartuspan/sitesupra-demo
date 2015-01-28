<?php

namespace Sample\Blocks;

use Supra\Package\Cms\Pages\Block\Config\BlockConfig;
use Supra\Package\Cms\Pages\Block\Mapper\AttributeMapper;
use Supra\Package\Cms\Pages\Block\Mapper\PropertyMapper;

class Gallery2 extends BlockConfig
{
	protected function configureAttributes(AttributeMapper $mapper)
	{
		$mapper->title('Gallery Example 2')
				->icon('sample:blocks/gallery.png')
		;
	}

	protected function configureProperties(PropertyMapper $mapper)
	{
		$mapper->autoDiscover();
	}
}
