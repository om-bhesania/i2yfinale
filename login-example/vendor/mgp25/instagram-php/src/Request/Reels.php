<?php

namespace InstagramAPI\Request;

use InstagramAPI\Request;
use InstagramAPI\Constants;
use InstagramAPI\Response;
use InstagramAPI\Signatures;
use InstagramAPI\Utils;

/**
 * Functions for interacting with Reels items from yourself and others.
 */
class Reels extends RequestCollection
{
    /**
     * Uploads a video to your Instagram reels feed
     *
     * @param string $videoFilename    The video filename.
     * @param array  $externalMetadata (optional) User-provided metadata key-value pairs.
     *
     * @throws \InvalidArgumentException
     * @throws \InstagramAPI\Exception\InstagramException
     * @throws \InstagramAPI\Exception\UploadFailedException If the video upload fails.
     *
     * @return \InstagramAPI\Response\ConfigureResponse
     *
     * @see Internal::configureSingleVideo() for available metadata fields.
     */
    public function uploadVideo(
        $videoFilename,
        array $externalMetadata = [])
    {
        return $this->ig->internal->uploadSingleVideo(Constants::FEED_CLIP, $videoFilename, null, $externalMetadata);
    }
    
    /**
     * Get a user's Reels feed.
     *
     * @param string      $userId   Numerical UserPK ID.
     * @param string      $pageSize Number of reels.
     * @param string|null $maxId    Next "maximum ID", used for pagination.
     *
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\UserClipsResponse
     */
    public function getUserFeed(
        $userId,
        $maxId = null,
        $pageSize = 12)
    {
        $request = $this->ig->request("clips/user/")
            ->addPost('_uuid', $this->ig->uuid)
            ->addPost('target_user_id', $userId);

        if (!empty($maxId)) {
            $request->addPost('max_id', $maxId);
        }

        if (!empty($pageSize)) {
            $request->addPost('page_size', $pageSize);
        }

        return $request->getResponse(new Response\UserClipsResponse);
    }

    /**
     * Get a Discover Reels feed.
     * 
     * @param string      $container_module   Module, from which action was made.
     * @param string|null $maxId              Next "maximum ID", used for pagination.
     * @param string      $chaining_media_id  Last media ID in previous response
     * @param array       $session_info       Additional server info 
     * @param array       $seen_reels         Array of reels in Instagram internal format
     * 
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\DiscoverClipsResponse
     */
    public function getDiscoverFeed(
        $container_module = 'clips_viewer_clips_tab',
        $maxId = null,
        $chaining_media_id = null,
        $session_info = [],
        $seen_reels = [])
    {
        $request = $this->ig->request("clips/discover/")
            ->addPost('_uuid', $this->ig->uuid);

        if (!empty($maxId)) {
            $request->addPost('max_id', $maxId)
                    ->addPost('container_module', 'clips_viewer_explore_popular_minor_unit');
            
            if (!empty($chaining_media_id)) {
                $request->addPost('chaining_media_id', $chaining_media_id);
            }

            if (!empty($session_info) && is_array($session_info)) {
                $request->addPost('session_info', json_encode($session_info));
            }

            if (!empty($seen_reels) && is_array($seen_reels)) {
                $request->addPost('session_info', json_encode($seen_reels));
            }
        } else {
            $request->addPost('container_module', $container_module);
        }

        return $request->getResponse(new Response\DiscoverClipsResponse);
    }

    /**
     * Get a Reels feed for music
     * 
     * @param string      $original_sound_audio_asset_id Audio Asset ID in Instagram internal format
     * @param string      $pageSize                      Number of reels.
     * @param string|null $maxId                         Next "maximum ID", used for pagination.
     * 
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\MusicClipsResponse
     */
    public function getMusicFeed(
        $asset_id,
        $maxId = null,
        $pageSize = 12)
    {
        $request = $this->ig->request("clips/music/")
            ->addPost('_uuid', $this->ig->uuid)
            ->addPost('original_sound_audio_asset_id', $asset_id);

        if (!empty($maxId)) {
            $request->addPost('max_id', $maxId);
        }

        if (!empty($pageSize)) {
            $request->addPost('page_size', $pageSize);
        }

        return $request->getResponse(new Response\MusicClipsResponse);
    }

    /**
     * Get a Hashtag Reels feed.
     * 
     * @param string      $hashtag Hashtag    
     * @param string|null $maxId   Next "maximum ID", used for pagination.
     * 
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\HashtagClipsResponse
     */
    public function getHashtagFeed(
        $hashtag = '',
        $maxId = null)
    {
        $request = $this->ig->request("clips/tags/{$hashtag}/")
            ->addPost('_uuid', $this->ig->uuid);

        if (!empty($maxId)) {
            $request->addPost('max_id', $maxId);
        }

        return $request->getResponse(new Response\HashtagClipsResponse);
    }
    
    /**
     * Mark Reels as seen
     * 
     * @param array $reels_ids Array of reels in Instagram internal format
     * 
     * @throws \InvalidArgumentException
     * @throws \InstagramAPI\Exception\InstagramException
     *
     * @return \InstagramAPI\Response\GenericResponse
     */
    public function markReelsAsSeen(
        $reels_ids)
    {
        if (!is_array($reels_ids)) {
            throw new \InvalidArgumentException('Invalid array of reels sent to markReelsAsSeen() function.');
        }

        return $this->ig->request("clips/write_seen_state/")
            ->addPost('impressions', json_encode(json_encode($reels_ids)))
            ->addPost('_uid', $this->ig->account_id)
            ->addPost('_uuid', $this->ig->uuid)
            ->getResponse(new Response\GenericResponse);
    }   
}