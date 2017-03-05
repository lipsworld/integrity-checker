<?php
namespace integrityChecker;

class AdminUIHooks
{
    private $pluginInfo = null;
    public function register()
    {
        global $pagenow;

        $plugin = integrityChecker::getInstance();

        add_action('load-plugins.php', array($this, 'loadPlugins'));
        add_filter('plugin_action_links_' . $plugin->getPluginBaseName(), array($this, 'pluginActionLinks'), 10, 1);
        if ($pagenow == 'update.php') {
            add_filter('site_transient_update_plugins', array($this, 'modifyPluginsTransient'), 99, 2);
        }


    }

    /**
     * Determine which plugins that have issues and hook them to after_plugin_row
     */
    public function loadPlugins()
    {
        $slugs = array();
        $allPlugins = get_site_transient('update_plugins');
        if ($allPlugins && isset($allPlugins->checked)) {
            foreach ($allPlugins->checked as $key => $value) {
                $slug = explode('/', $key)[0];
                $slugs[$slug] = $key;
            };
        }

        $state = new State();
        $checksumResults = $state->getTestResult('checksum');
        if ($checksumResults && isset($checksumResults['plugins'])) {
            foreach ($checksumResults['plugins'] as $plugin) {
                if ($plugin->hardIssues > 0) {
                    if (isset($slugs[$plugin->slug]) && !isset($allPlugins->response[$slugs[$plugin->slug]])) {
                        add_action("after_plugin_row_{$slugs[$plugin->slug]}", array($this, 'offerUpdate'), 10, 3);
                    }
                }
            }
        }
    }

    public function offerUpdate($pluginFile, $pluginData, $status)
    {

        $pluginsAllowedtags = array(
            'a'       => array('href' => array(), 'title' => array()),
            'abbr'    => array('title' => array()),
            'acronym' => array('title' => array()),
            'code'    => array(),
            'em'      => array(),
            'strong'  => array(),
        );

        $current = get_site_transient( 'update_plugins' );
        $response = $current->checked[$pluginFile];

        $pluginName   = wp_kses($pluginData['Name'], $pluginsAllowedtags);
        $slug = $pluginData['slug'];
        $upgradeUrl = wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&plugin=') .
                                   $pluginFile, 'upgrade-plugin_' . $pluginFile);
        include __DIR__ . '/Admin/views/PluginUpdateAlert.php';
    }

    public function pluginActionLinks($links)
    {
        $plugin = integrityChecker::getInstance();
        $url = admin_url('tools.php?page=' . $plugin->getPluginSlug(). '_options');

        return array_merge(
            $links,
            array(
                '<a href="' . $url . '">Settings</a>'
            )
        );
    }

    public function modifyPluginsTransient($value, $transient)
    {
        // Modify the transient to slip by WP's same version check
        $pluginFile = isset($_REQUEST['plugin']) ? trim($_REQUEST['plugin']): '';
        if (strlen($pluginFile) && !isset($value->response[$pluginFile])) {
            $value->response[$pluginFile] = $this->getPluginInfo($pluginFile);
        }

        return $value;
    }

    private function getPluginInfo($pluginFile)
    {
        require_once ABSPATH.'/wp-admin/includes/plugin-install.php';
        if (is_null($this->pluginInfo)) {
            $slug = explode('/', $pluginFile)[0];
            $pluginApi = plugins_api(
                'plugin_information',
                array( 'slug' => $slug,
                       'fields' => array('sections' => false, 'compatibility' => false, 'tags' => false)
                )
            );
            $this->pluginInfo = (object)array(
                'slug'        => $slug,
                'new_version' => $pluginApi->version,
                'package'     => $pluginApi->download_link
            );
        }

        return $this->pluginInfo;
    }
}


