<?php if (!defined('APP_VERSION')) die("Yo, what's up?"); ?>

<div class='skeleton' id="account">
    <form class="js-ajax-form" 
          action="<?= APPURL . "/e/" . $idname . "/settings" ?>"
          method="POST">
        <input type="hidden" name="action" value="save">

        <div class="container-1200">
            <div class="row clearfix">
                <div class="form-result">
                </div>

                <div class="col s12 m8 l4">
                    <section class="section mb-20">
                        <div class="section-content border-after">
                            <div class="mb-10 clearfix">
                                <div class="col s12 m12 l12">
                                    <?php $license_key = $Settings->get("data.license_key"); ?>
                                    <label class="form-label"><?= __("License Key") ?></label>
                                    <input class="input license-key" name="license-key" id="license-key" type="text" maxlength="50" placeholder="<?= __("Enter the key") ?>" value="<?= htmlchars($license_key) ?>">
                                </div>
                            </div>

                            <ul class="field-tips">
                                <?php 
                                    $license = $Settings->get("data.license"); 
                                    if ($license != "valid"):
                                ?>
                                    <li><?= __("Please type a valid license key, which you can find in your %s.", "<a href='https://nextpost.tech/dashboard/' target='_blank'>Nextpost.tech Dashboard</a>") ?></li>
                                <?php else: ?>
                                    <?php if ($Settings->get("data.item_name")): ?>
                                        <li><?= __("Product name: ") ?><?= htmlchars($Settings->get("data.item_name")) ?></li>
                                    <?php endif; ?>
                                    <?php if ($Settings->get("data.expires") && $Settings->get("data.license") == "valid"): ?>
                                        <li class="color-green"><?= __("License expire: ") ?><?= htmlchars($Settings->get("data.expires")) ?></li>
                                        <li><?= " " . __("You can renew your or update your existing license %s.", "<a href='https://nextpost.tech/dashboard/' target='_blank'>here</a>") ?></li>
                                    <?php endif; ?>
                                    <?php if ($Settings->get("data.payment_id")): ?>
                                        <li><?= __("Payment ID: ") ?><?= htmlchars($Settings->get("data.payment_id")) ?></li>
                                    <?php endif; ?>
                                    <?php if ($Settings->get("data.customer_name")): ?>
                                        <li><?= __("Customer name: ") ?><?= htmlchars($Settings->get("data.customer_name")) ?></li>
                                    <?php endif; ?>
                                    <?php if ($Settings->get("data.customer_email")): ?>
                                        <li><?= __("Customer email: ") ?><?= htmlchars($Settings->get("data.customer_email")) ?></li>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <div class="section-content border-after">
                            <div class="mb-10 clearfix">
                                <div class="col s12 m12 l12">
                                    <?php $custom_proxy = $Settings->get("data.custom_proxy"); ?>
                                    <label class="form-label"><?= __("Custom Proxy") ?></label>
                                    <input class="input custom-proxy" name="custom-proxy" id="custom-proxy" type="text" maxlength="150" placeholder="<?= __("Enter the proxy") ?>" value="<?= htmlchars($custom_proxy) ?>">
                                </div>
                            </div>

                            <ul class="field-tips">
                                <li><?= __("We recommend you use a mobile proxy to fix empty captions issue in Instagram posts.") ?></li>
                                <li><?= __("This proxy will be used only for post media requests.") ?></li>
                                <li><?= __("We suggest you %s mobile proxies.", "<a href='https://ltespace.com/sergeykomlev' target='_blank'>LTEspace</a>") ?></li>
                            </ul>
                        </div>

                        <div class="section-header clearfix">
                            <h2 class="section-title"><?= __("Speeds") ?></h2>
                        </div>

                        <div class="section-content">
                            <div class="mb-10 clearfix">
                                <div class="col s6 m6 l6">
                                    <label class="form-label"><?= __("Speed 1") ?></label>

                                    <select name="speed-very-slow" class="input">
                                        <?php 
                                            $s = $Settings->get("data.speeds.very_slow")
                                        ?>
                                        <?php for ($i=1; $i<=72; $i++): ?>
                                            <option value="<?= $i ?>" <?= $i == $s ? "selected" : "" ?>>
                                                <?= n__("%s request/day", "%s requests/day", $i, $i) ?>                                                  
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>

                                <div class="col s6 s-last m6 m-last l6 l-last mb-20">
                                    <label class="form-label"><?= __("Speed 2") ?></label>

                                    <select name="speed-slow" class="input">
                                        <?php 
                                            $s = $Settings->get("data.speeds.slow")
                                        ?>
                                        <?php for ($i=1; $i<=72; $i++): ?>
                                            <option value="<?= $i ?>" <?= $i == $s ? "selected" : "" ?>>
                                                <?= n__("%s request/day", "%s requests/day", $i, $i) ?>                                                    
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-10 clearfix">
                                <div class="col s6 m6 l6">
                                    <label class="form-label"><?= __("Speed 3") ?></label>

                                    <select name="speed-medium" class="input">
                                        <?php 
                                            $s = $Settings->get("data.speeds.medium")
                                        ?>
                                        <?php for ($i=1; $i<=72; $i++): ?>
                                            <option value="<?= $i ?>" <?= $i == $s ? "selected" : "" ?>>
                                                <?= n__("%s request/day", "%s requests/day", $i, $i) ?>                                                    
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>

                                <div class="col s6 s-last m6 m-last l6 l-last mb-20">
                                    <label class="form-label"><?= __("Speed 4") ?></label>

                                    <select name="speed-fast" class="input">
                                        <?php 
                                            $s = $Settings->get("data.speeds.fast")
                                        ?>
                                        <?php for ($i=1; $i<=72; $i++): ?>
                                            <option value="<?= $i ?>" <?= $i == $s ? "selected" : "" ?>>
                                                <?= n__("%s request/day", "%s requests/day", $i, $i) ?>                                                    
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-30 clearfix">
                                <div class="col s6 m6 l6">
                                    <label class="form-label"><?= __("Speed 5") ?></label>

                                    <select name="speed-very-fast" class="input">
                                        <?php 
                                            $s = $Settings->get("data.speeds.very_fast")
                                        ?>
                                        <?php for ($i=1; $i<=72; $i++): ?>
                                            <option value="<?= $i ?>" <?= $i == $s ? "selected" : "" ?>>
                                                <?= n__("%s request/day", "%s requests/day", $i, $i) ?>                                                    
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>

                            <ul class="field-tips">
                                <li><?= __("These values indicates exact amount of the reposts per day.") ?></li>
                                <li><?= __("High speeds might be risky") ?></li>
                                <li><?= __("Developers are not responsible for any issues related to the Instagram accounts.") ?></li>
                            </ul>

                            <div class="mt-30 mb-10 clearfix">
                                <div class="col s12 m12 l12">
                                    <?php $cf_worker = $Settings->get("data.cf_worker"); ?>
                                    <label class="form-label"><?= __("Cloudflare worker (for CORS)") ?></label>
                                    <input class="input cf-worker" name="cf-worker" id="cf-worker" type="text" maxlength="50" placeholder="<?= __("Enter the url") ?>" value="<?= htmlchars($cf_worker) ?>">
                                </div>
                            </div>

                            <ul class="field-tips">
                                <li><?= __("Cloudflare worker to bypass Instagram new cross-origin policy on images (ERR_BLOCKED_BY_RESPONSE).") ?></li>
                                <li><?= __("Create new worker and put the code below into the worker code:") ?></li>
                            </ul>

                            <pre style="width: auto; font-size: 12px; background-color: #1E1E1E; color: #cccccc; display: block; overflow-x: auto; overflow-y: auto; padding: 16px; border: 1px solid #e0e0e0; border-radius: 7px;">
async function handleRequest(request) {
let url = new URL(request.url)
if (!(request.headers.has('referer') && request.headers.get('referer').startsWith('<?= APPURL ?>/'))) {
    return new Response('Invalid referer. Please use your own Cloudflare workers.', {status: 400})
}
let newUrl = url.pathname.replace(/^\/+/, '').replace('https:/', 'https://') + url.search
let response = await fetch(newUrl.toString(), request)
response = new Response(response.body, response)
response.headers.set('cross-origin-resource-policy', 'cross-origin')
return response;
}

addEventListener('fetch', event => {
event.respondWith(handleRequest(event.request))
})</pre>
                        </div>
                    </section>
                </div>

                <div class="col s12 m8 l4">
                    <section class="section">
                        <div class="section-header clearfix">
                            <h2 class="section-title"><?= __("Other Settings") ?></h2>
                        </div>

                        <div class="section-content">
                            <div class="mb-20">
                                <label>
                                    <input type="checkbox" 
                                           class="checkbox" 
                                           name="random_delay" 
                                           value="1" 
                                           <?= $Settings->get("data.random_delay") ? "checked" : "" ?>>
                                    <span>
                                        <span class="icon unchecked">
                                            <span class="mdi mdi-check"></span>
                                        </span>
                                        <?= __('Enable Random Delays') ?>
                                        (<?= __("Recommended") ?>)

                                        <ul class="field-tips">
                                            <li><?= __("If you enable this option, script will add random delays automatically between each requests.") ?></li>
                                            <li><?= __("Delays could be up to 60 minutes.") ?></li>
                                        </ul>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <input class="fluid button button--footer" type="submit" value="<?= __("Save") ?>">
                    </section>
                </div>
            </div>
        </div>
    </form>
</div>