<?php

	// Exit if accessed directly
	if( !defined('ABSPATH') ) {
		exit;
	}
	if( !class_exists('Wbcr_FactoryTypes415_Menu') ) {
		class Wbcr_FactoryTypes415_Menu {

			public $icon;

			/**
			 * A custom post type that is configurated by this instance.
			 * @var Wbcr_FactoryTypes415_Type
			 */
			public $type = null;

			/**
			 * @param Wbcr_FactoryTypes415_Type $type
			 */
			public function __construct(Wbcr_FactoryTypes415_Type $type)
			{
				$this->type = $type;
			}
		}
	}