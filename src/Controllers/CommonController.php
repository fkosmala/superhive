<?php

/**
 *  * Common controller
 *  *
 * The file contains every function needed in many other controllers.
 *
 *  * @category   Controllers
 *  * @package    SuperHive
 *  * @author     Florent Kosmala <kosflorent@gmail.com>
 *  * @license    https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0
 *  */

declare(strict_types=1);

namespace App\Controllers;

use Hive\PhpLib\Hive\Condenser as HiveCondenser;
use Psr\Container\ContainerInterface;

final class CommonController
{
    private ContainerInterface $app;

    public function __construct(ContainerInterface $app)
    {
        $this->app = $app;
    }

    /**
     *  * genPostsFile function
     *  *
     * This function will query the blockchain to have posts
     * It will generate a JSON file stored in /resources/blog/ folder
     *  */
    public function genPostsFile(): void
    {
        $settings = $this->app->get('settings');
        $result = '';

        // Hive API communication init
        $apiConfig = [
            'hiveNode' => $settings['api'],
            'debug' => false,
        ];
        $api = new HiveCondenser($apiConfig);

        // The file with the latest posts.
        $file = $this->app->get('blogfile');

        // if the JSON file doesn't exist or if it's old, take it from API
        if (!file_exists($file) || (time() - filemtime($file) > $settings['delay'])) {
            // Prepare API call according to displayed posts type
            $displayType = $settings['displayType']['type'];
            if ($displayType === 'author') {
                $dateNow = (new \DateTime())->format('Y-m-d\TH:i:s');
                $result = json_encode(
                    $api->getDiscussionsByAuthorBeforeDate(
                        $settings['author'],
                        '',
                        $dateNow,
                        100
                    ),
                    JSON_PRETTY_PRINT
                );
            } elseif (($displayType === 'tag')) {
                $displayTag = $settings['displayType']['tag'];
                $taggedPosts = [];
                $allPosts = json_encode(
                    $api->getDiscussionsByAuthorBeforeDate(
                        $settings['author'],
                        '',
                        '',
                        100
                    )
                );
                $allPosts = json_decode($allPosts, true);
                //print_r($allPosts);
                foreach ($allPosts as &$post) {
                    //$postTags = json_encode($post['json_metadata'], JSON_PRETTY_PRINT);
                    $postMeta = json_decode($post['json_metadata'], true);
                    $postTags = $postMeta['tags'];
                    if (in_array($displayTag, $postTags)) {
                        $taggedPosts[] = $post;
                    }
                }

                $result = json_encode($taggedPosts, JSON_PRETTY_PRINT);
                unset($taggedPosts);
            } elseif ($displayType === 'reblog') {
                $result = json_encode($api->getDiscussionsByBlog($settings['author']), JSON_PRETTY_PRINT);
            } elseif (strpos($settings['author'], 'hive-') === 0) {
                $result = json_encode($api->getDiscussionsByCreated($settings['author']), JSON_PRETTY_PRINT);
            }
            file_put_contents($file, $result);
        }
    }

    /**
     *  * getMostUsedTags function
     *  *
     * This function will get tags from eac post and sort them
     * by occurence.
     *
     * @return array<string, int> $mostUsedTags
     *  */
    public function getMostUsedTags(): array
    {
        $file = $this->app->get('blogfile');
        $tags = '';

        $data = json_decode(file_get_contents($file), true);

        /* For each post, get metadata, to extract tags only */
        foreach ($data as &$post) {
            $meta = json_decode($post['json_metadata'], true);
            $tags .= implode(',', $meta['tags']) . ',';
        }

        $tagsArray = explode(',', $tags);
        $mostUsedTags = array_count_values($tagsArray); //get all occurrences of each values
        arsort($mostUsedTags);
        $mostUsedTags = array_slice($mostUsedTags, 0, 10, true);

        return $mostUsedTags;
    }
}
