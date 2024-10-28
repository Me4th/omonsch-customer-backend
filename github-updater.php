<?php
// GitHub Plugin Updater Class einfügen
if (!class_exists('WP_GitHub_Updater')) {
    class WP_GitHub_Updater {
        private $pluginFile;
        private $githubRepo;
        private $pluginData;
        private $githubAPIResult;

        public function __construct($pluginFile) {
            $this->pluginFile = $pluginFile;
            $this->githubRepo = 'Me4th/omonsch-customer-backend';
            add_filter("pre_set_site_transient_update_plugins", [$this, "setPluginTransient"]);
            add_filter("plugins_api", [$this, "setPluginInfo"], 10, 3);
        }

        // Plugin-Update-Information abrufen
        private function getRepoReleaseInfo() {
            if (is_null($this->githubAPIResult)) {
                $requestUri = "https://api.github.com/repos/{$this->githubRepo}/releases/latest";
                $response = wp_remote_get($requestUri);
                $this->githubAPIResult = json_decode(wp_remote_retrieve_body($response), true);
            }
            return $this->githubAPIResult;
        }

        // Transienten für Plugin-Update setzen
        public function setPluginTransient($transient) {
            if (empty($transient->checked)) {
                return $transient;
            }
            $releaseInfo = $this->getRepoReleaseInfo();
            $latestVersion = $releaseInfo['tag_name'];
            $currentVersion = $transient->checked[$this->pluginFile];
            if (version_compare($currentVersion, $latestVersion, '<')) {
                $transient->response[$this->pluginFile] = (object) [
                    'slug' => $this->pluginFile,
                    'new_version' => $latestVersion,
                    'package' => $releaseInfo['zipball_url']
                ];
            }
            return $transient;
        }

        // Plugin-Informationen für den WordPress-Updater bereitstellen
        public function setPluginInfo($result, $action, $args) {
            if ($args->slug !== $this->pluginFile) return $result;
            $releaseInfo = $this->getRepoReleaseInfo();
            return (object) [
                'name' => 'Oliver Monschau - Customer Backend',
                'slug' => $this->pluginFile,
                'version' => $releaseInfo['tag_name'],
                'download_link' => $releaseInfo['zipball_url'],
            ];
        }
    }

    new WP_GitHub_Updater(plugin_basename(__FILE__));
}
