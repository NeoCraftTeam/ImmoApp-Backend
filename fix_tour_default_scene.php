<?php

$ads = App\Models\Ad::query()->whereNotNull('tour_config')->get();

foreach ($ads as $ad) {
    $config = $ad->tour_config;
    if (!empty($config['scenes']) && empty($config['default_scene'])) {
        $config['default_scene'] = $config['scenes'][0]['id'];
        $ad->updateQuietly(['tour_config' => $config]);
        echo 'Fixed ad '.$ad->id."\n";
    }
}

echo 'Done'."\n";
