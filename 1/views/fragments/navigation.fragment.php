<?php 
	// Disable direct access
    if (!defined('APP_VERSION')) 
        die("Yo, what's up?");  
	
	// Check is this plugin is active
    if (!isset($GLOBALS["_PLUGINS_"][$idname]["config"]))
          return null;
          
	// Get this module's config data
    $config = $GLOBALS["_PLUGINS_"][$idname]["config"]; 
    
    // Get authenticated user's active moduless
    $user_modules = $AuthUser->get("settings.modules");
    if (empty($user_modules)) {
        $user_modules = [];
    }
?>

<?php if (in_array($idname, $user_modules)): ?>
<li class="<?= $Nav->activeMenu == $idname ? "active" : "" ?>">
    <a href="<?= APPURL."/e/".$idname ?>">
        <span class="special-menu-icon" style="<?= empty($config["icon_style"]) ? "" : $config["icon_style"] ?>">
            <span class="sli sli-reload"></span>
        </span>

        <span class="label"><?= __("Repost Pro") ?></span>

        <span class="tooltip tippy" 
              data-position="right"
              data-delay="100" 
              data-arrow="true"
              data-distance="-1"
              title="<?= __("Repost Pro") ?>"></span>
    </a>
</li>
<?php endif ?>