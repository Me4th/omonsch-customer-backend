<?php
// GitHub Plugin Updater Class einfügen
if (!class_exists('WP_GitHub_Updater')) {
    error_log("GitHub Updater geladen");
    class WP_GitHub_Updater {
        private $pluginFile;
        private $githubRepo;
        private $pluginData;
        private $githubAPIResult;

        public function __construct($pluginFile) {
            $this->pluginFile = $pluginFile;
            $this->slug = plugin_basename($pluginFile); // Sicherstellen, dass der Slug richtig gesetzt ist
            $this->githubRepo = 'Me4th/omonsch-customer-backend'; // Repository-Name

            add_filter("pre_set_site_transient_update_plugins", [$this, "setPluginTransient"]);
            add_filter("plugins_api", [$this, "setPluginInfo"], 10, 3);
        }

        // Plugin-Update-Information abrufen
        private function getRepoReleaseInfo() {
            if (is_null($this->githubAPIResult)) {
                $requestUri = "https://api.github.com/repos/{$this->githubRepo}/releases/latest";
                $args = [
                    'headers' => [
                        'Authorization' => 'Bearer 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',  // Nutze Bearer statt token
                        'User-Agent' => 'WordPress-Updater'  // GitHub benötigt oft einen User-Agent
                    ]
                ];
                $response = wp_remote_get($requestUri, $args);
                $this->githubAPIResult = json_decode(wp_remote_retrieve_body($response), true);

                // Log für vollständige Antwort zur Überprüfung
                error_log("GitHub API Full Response with Token: " . print_r($this->githubAPIResult, true));
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
            error_log("Aktuelle Version: " . $currentVersion);
            error_log("Neueste Version auf GitHub: " . $latestVersion);
            if (version_compare($currentVersion, $latestVersion, '<')) {
                $transient->response[$this->pluginFile] = (object) [
                    'slug' => $this->pluginFile,
                    'new_version' => $latestVersion,
                    'package' => $releaseInfo['zipball_url']
                ];
            }
            if (!empty($transient->response[$this->pluginFile])) {
                error_log("Update für Plugin verfügbar: " . print_r($transient->response[$this->pluginFile], true));
            } else {
                error_log("Kein Update verfügbar.");
            }
            return $transient;
        }

        // Plugin-Informationen für den WordPress-Updater bereitstellen
        public function setPluginInfo($result, $action, $args) {
            if ($args->slug !== $this->slug) return $result; // Korrektur beim Slug-Vergleich

            $releaseInfo = $this->getRepoReleaseInfo();
            return (object) [
                'name' => 'Oliver Monschau - Customer Backend',
                'slug' => $this->slug,
                'version' => $releaseInfo['tag_name'],
                'download_link' => $releaseInfo['zipball_url'],
            ];
        }
    }
}
add_filter('upgrader_source_selection', function($source, $remote_source, $upgrader) {
    $plugin_slug = 'omonsch-customer-backend'; // Der gewünschte Plugin-Ordnername
    $corrected_path = trailingslashit(WP_PLUGIN_DIR) . $plugin_slug;

    // Debugging-Log: Pfade vor der Umbenennung
    error_log("Debugging: Original-Ordnerpfad ist {$source}");
    error_log("Debugging: Ziel-Ordnerpfad ist {$corrected_path}");

    // Prüfen, ob der Ordner bereits den gewünschten Namen hat
    if (basename($source) !== $plugin_slug) {
        // Versuche, den Ordner umzubenennen
        if (rename($source, $corrected_path)) {
            error_log("Ordner erfolgreich von {$source} nach {$corrected_path} umbenannt.");
            return $corrected_path; // Korrigierter Pfad wird zurückgegeben
        } else {
            // Fehlerprotokollierung bei fehlgeschlagenem Umbenennen
            error_log("Fehler: Das Umbenennen des Plugin-Ordners von {$source} nach {$corrected_path} ist fehlgeschlagen.");
            return new WP_Error('rename_failed', __('Das Umbenennen des Plugin-Ordners ist fehlgeschlagen.'));
        }
    } else {
        error_log("Ordnername ist korrekt, keine Umbenennung erforderlich.");
    }
    return $source;
}, 10, 3);



