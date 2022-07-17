<form action="<?= $form_action ?>" class="search-box" method="GET" autocomplete="off">
    <div class="pos-r">
        <input type="text" class="input input--small leftpad rightpad" name="q" placeholder="<?= __("Search...") ?>" value="<?= htmlchars(\Input::get("q")) ?>">
        <span class="small field-icon--right processing">
            <img src="<?= APPURL."/assets/img/round-loading.svg" ?>" alt="Searching...">
        </span>
        <span class="sli sli-magnifier small field-icon--left search-icon"></span>
        <a href="javascript:void(0)" class="mdi mdi-arrow-left small field-icon--left cancel-icon"></a>
    </div>
    <div class="pos-r">
        <input type="hidden" class="input" name="only_active" value="<?= $AuthUser->get("data.repost_pro_only_active") ? 1 : 0 ?>">
        <?php 
            $only_active_cv = $AuthUser->get("data.repost_pro_only_active"); 
            if ($only_active_cv): 
        ?>
            <a class="mt-10 button small js-only-active active"><?= __("Only active") ?></a>
        <?php else: ?>
            <a class="mt-10 button small button--light-outline js-only-active"><?= __("Only active") ?></a>
        <?php endif ?>
    </div>
</form>