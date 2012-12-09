<?php
/**
 * Database migrations based on ideas from Ruby on Rails and Google Gears.
 *
 * @package   Smoke_Migration
 * @author    Radoslav Kirilov <https://github.com/smoke>
 * @copyright Radoslav Kirilov <https://github.com/smoke>
 */

/**
 * A Zend_Tool_Project_Provider manifest for the Smoke Migration package
 *
 * @author Radoslav Kirilov
 * @see Zend_Tool_Framework_Manifest_ProviderManifestable
 * @package Smoke_Migration
 * @subpackage ZendToolProvider
 */
class Smoke_Migration_Zend_ToolProvider_Manifest implements Zend_Tool_Framework_Manifest_ProviderManifestable
{
    public function getProviders()
    {
        require_once 'Smoke/Migration/Zend/ToolProvider/Migrations.php';
        return array(
            new Smoke_Migration_Zend_ToolProvider_Migrations()
        );
    }
}
