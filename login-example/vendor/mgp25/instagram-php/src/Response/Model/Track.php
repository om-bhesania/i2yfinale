<?php

namespace InstagramAPI\Response\Model;

use InstagramAPI\AutoPropertyMapper;

/**
 * Track.
 *
 * @method string getAudioClusterId()
 * @method string getId()
 * @method string getTitle()
 * @method string getSubtitle()
 * @method string getDisplayArtist()
 * @method string getCoverArtworkUri()
 * @method string getCoverArtworkThumbnailUri()
 * @method string getProgressiveDownloadUrl()
 * @method mixed  getReactiveAudioDownloadUrl()
 * @method mixed  getHighlightStartTimesInMs()
 * @method bool   getIsExplicit()
 * @method mixed  getDashManifest()
 * @method bool   getHasLyrics()
 * @method string getAudioAssetId()
 * @method int    getDurationInMs()
 * @method mixed  getDarkMessage()
 * @method bool   getAllowsSaving()
 * @method bool   isAudioClusterId()
 * @method bool   isId()
 * @method bool   isTitle()
 * @method bool   isSubtitle()
 * @method bool   isDisplayArtist()
 * @method bool   isCoverArtworkUri()
 * @method bool   isCoverArtworkThumbnailUri()
 * @method bool   isProgressiveDownloadUrl()
 * @method bool   isReactiveAudioDownloadUrl()
 * @method bool   isHighlightStartTimesInMs()
 * @method bool   isIsExplicit()
 * @method bool   isDashManifest()
 * @method bool   isHasLyrics()
 * @method bool   isAudioAssetId()
 * @method bool   isDurationInMs()
 * @method bool   isDarkMessage()
 * @method bool   isAllowsSaving()
 * @method $this  setAudioClusterId(string $value)
 * @method $this  setId(string $value)
 * @method $this  setTitle(string $value)
 * @method $this  setSubtitle(string $value)
 * @method $this  setDisplayArtist(string $value)
 * @method $this  setCoverArtworkUri(string $value)
 * @method $this  setCoverArtworkThumbnailUri(string $value)
 * @method $this  setProgressiveDownloadUrl(string $value)
 * @method $this  setReactiveAudioDownloadUrl(mixed $value)
 * @method $this  setHighlightStartTimesInMs(mixed $value)
 * @method $this  setIsExplicit(bool $value)
 * @method $this  setDashManifest(mixed $value)
 * @method $this  setHasLyrics(bool $value)
 * @method $this  setAudioAssetId(string $value)
 * @method $this  setDurationInMs(int $value)
 * @method $this  setDarkMessage(mixed $value)
 * @method $this  setAllowsSaving(bool $value)
 * @method $this  unsetAudioClusterId()
 * @method $this  unsetId()
 * @method $this  unsetTitle()
 * @method $this  unsetSubtitle()
 * @method $this  unsetDisplayArtist()
 * @method $this  unsetCoverArtworkUri()
 * @method $this  unsetCoverArtworkThumbnailUri()
 * @method $this  unsetProgressiveDownloadUrl()
 * @method $this  unsetReactiveAudioDownloadUrl()
 * @method $this  unsetHighlightStartTimesInMs()
 * @method $this  unsetIsExplicit()
 * @method $this  unsetDashManifest()
 * @method $this  unsetHasLyrics()
 * @method $this  unsetAudioAssetId()
 * @method $this  unsetDurationInMs()
 * @method $this  unsetDarkMessage()
 * @method $this  unsetAllowsSaving()
 */
class Track extends AutoPropertyMapper
{
    const JSON_PROPERTY_MAP = [
        'audio_cluster_id' => 'string',
        'id' => 'string',
        'title' => 'string',
        'subtitle' => 'string', 
        'display_artist' => 'string',
        'cover_artwork_uri' => 'string',
        'cover_artwork_thumbnail_uri' => 'string',
        'progressive_download_url' => 'string',
        'reactive_audio_download_url' => '',
        'highlight_start_times_in_ms' => '',
        'is_explicit'  => 'bool',
        'dash_manifest' => '',
        'has_lyrics' => 'bool',
        'audio_asset_id' => 'string',
        'duration_in_ms' => 'int',
        'dark_message' => '',
        'allows_saving' => 'bool'
    ];
}
