/**
 * RepostPro Namespane
 */
var RepostPro = {};

!function(t,n){"object"==typeof exports&&"undefined"!=typeof module?module.exports=n():"function"==typeof define&&define.amd?define(n):(t=t||self).LazyLoad=n()}(this,(function(){"use strict";function t(){return(t=Object.assign||function(t){for(var n=1;n<arguments.length;n++){var e=arguments[n];for(var i in e)Object.prototype.hasOwnProperty.call(e,i)&&(t[i]=e[i])}return t}).apply(this,arguments)}var n="undefined"!=typeof window,e=n&&!("onscroll"in window)||"undefined"!=typeof navigator&&/(gle|ing|ro)bot|crawl|spider/i.test(navigator.userAgent),i=n&&"IntersectionObserver"in window,o=n&&"classList"in document.createElement("p"),r=n&&window.devicePixelRatio>1,a={elements_selector:".lazy",container:e||n?document:null,threshold:300,thresholds:null,data_src:"src",data_srcset:"srcset",data_sizes:"sizes",data_bg:"bg",data_bg_hidpi:"bg-hidpi",data_bg_multi:"bg-multi",data_bg_multi_hidpi:"bg-multi-hidpi",data_poster:"poster",class_applied:"applied",class_loading:"loading",class_loaded:"loaded",class_error:"error",class_entered:"entered",class_exited:"exited",unobserve_completed:!0,unobserve_entered:!1,cancel_on_exit:!0,callback_enter:null,callback_exit:null,callback_applied:null,callback_loading:null,callback_loaded:null,callback_error:null,callback_finish:null,callback_cancel:null,use_native:!1},c=function(n){return t({},a,n)},s=function(t,n){var e,i="LazyLoad::Initialized",o=new t(n);try{e=new CustomEvent(i,{detail:{instance:o}})}catch(t){(e=document.createEvent("CustomEvent")).initCustomEvent(i,!1,!1,{instance:o})}window.dispatchEvent(e)},l="loading",u="loaded",d="applied",f="error",_="native",g="data-",v="ll-status",p=function(t,n){return t.getAttribute(g+n)},b=function(t){return p(t,v)},h=function(t,n){return function(t,n,e){var i="data-ll-status";null!==e?t.setAttribute(i,e):t.removeAttribute(i)}(t,0,n)},m=function(t){return h(t,null)},E=function(t){return null===b(t)},y=function(t){return b(t)===_},A=[l,u,d,f],I=function(t,n,e,i){t&&(void 0===i?void 0===e?t(n):t(n,e):t(n,e,i))},L=function(t,n){o?t.classList.add(n):t.className+=(t.className?" ":"")+n},w=function(t,n){o?t.classList.remove(n):t.className=t.className.replace(new RegExp("(^|\\s+)"+n+"(\\s+|$)")," ").replace(/^\s+/,"").replace(/\s+$/,"")},k=function(t){return t.llTempImage},O=function(t,n){if(n){var e=n._observer;e&&e.unobserve(t)}},x=function(t,n){t&&(t.loadingCount+=n)},z=function(t,n){t&&(t.toLoadCount=n)},C=function(t){for(var n,e=[],i=0;n=t.children[i];i+=1)"SOURCE"===n.tagName&&e.push(n);return e},N=function(t,n,e){e&&t.setAttribute(n,e)},M=function(t,n){t.removeAttribute(n)},R=function(t){return!!t.llOriginalAttrs},G=function(t){if(!R(t)){var n={};n.src=t.getAttribute("src"),n.srcset=t.getAttribute("srcset"),n.sizes=t.getAttribute("sizes"),t.llOriginalAttrs=n}},T=function(t){if(R(t)){var n=t.llOriginalAttrs;N(t,"src",n.src),N(t,"srcset",n.srcset),N(t,"sizes",n.sizes)}},j=function(t,n){N(t,"sizes",p(t,n.data_sizes)),N(t,"srcset",p(t,n.data_srcset)),N(t,"src",p(t,n.data_src))},D=function(t){M(t,"src"),M(t,"srcset"),M(t,"sizes")},F=function(t,n){var e=t.parentNode;e&&"PICTURE"===e.tagName&&C(e).forEach(n)},P={IMG:function(t,n){F(t,(function(t){G(t),j(t,n)})),G(t),j(t,n)},IFRAME:function(t,n){N(t,"src",p(t,n.data_src))},VIDEO:function(t,n){!function(t,e){C(t).forEach((function(t){N(t,"src",p(t,n.data_src))}))}(t),N(t,"poster",p(t,n.data_poster)),N(t,"src",p(t,n.data_src)),t.load()}},S=function(t,n){var e=P[t.tagName];e&&e(t,n)},V=function(t,n,e){x(e,1),L(t,n.class_loading),h(t,l),I(n.callback_loading,t,e)},U=["IMG","IFRAME","VIDEO"],$=function(t,n){!n||function(t){return t.loadingCount>0}(n)||function(t){return t.toLoadCount>0}(n)||I(t.callback_finish,n)},q=function(t,n,e){t.addEventListener(n,e),t.llEvLisnrs[n]=e},H=function(t,n,e){t.removeEventListener(n,e)},B=function(t){return!!t.llEvLisnrs},J=function(t){if(B(t)){var n=t.llEvLisnrs;for(var e in n){var i=n[e];H(t,e,i)}delete t.llEvLisnrs}},K=function(t,n,e){!function(t){delete t.llTempImage}(t),x(e,-1),function(t){t&&(t.toLoadCount-=1)}(e),w(t,n.class_loading),n.unobserve_completed&&O(t,e)},Q=function(t,n,e){var i=k(t)||t;B(i)||function(t,n,e){B(t)||(t.llEvLisnrs={});var i="VIDEO"===t.tagName?"loadeddata":"load";q(t,i,n),q(t,"error",e)}(i,(function(o){!function(t,n,e,i){var o=y(n);K(n,e,i),L(n,e.class_loaded),h(n,u),I(e.callback_loaded,n,i),o||$(e,i)}(0,t,n,e),J(i)}),(function(o){!function(t,n,e,i){var o=y(n);K(n,e,i),L(n,e.class_error),h(n,f),I(e.callback_error,n,i),o||$(e,i)}(0,t,n,e),J(i)}))},W=function(t,n,e){!function(t){t.llTempImage=document.createElement("IMG")}(t),Q(t,n,e),function(t,n,e){var i=p(t,n.data_bg),o=p(t,n.data_bg_hidpi),a=r&&o?o:i;a&&(t.style.backgroundImage='url("'.concat(a,'")'),k(t).setAttribute("src",a),V(t,n,e))}(t,n,e),function(t,n,e){var i=p(t,n.data_bg_multi),o=p(t,n.data_bg_multi_hidpi),a=r&&o?o:i;a&&(t.style.backgroundImage=a,function(t,n,e){L(t,n.class_applied),h(t,d),n.unobserve_completed&&O(t,n),I(n.callback_applied,t,e)}(t,n,e))}(t,n,e)},X=function(t,n,e){!function(t){return U.indexOf(t.tagName)>-1}(t)?W(t,n,e):function(t,n,e){Q(t,n,e),S(t,n),V(t,n,e)}(t,n,e)},Y=["IMG","IFRAME"],Z=function(t){return t.use_native&&"loading"in HTMLImageElement.prototype},tt=function(t,n,e){t.forEach((function(t){return function(t){return t.isIntersecting||t.intersectionRatio>0}(t)?function(t,n,e,i){h(t,"entered"),L(t,e.class_entered),w(t,e.class_exited),function(t,n,e){n.unobserve_entered&&O(t,e)}(t,e,i),I(e.callback_enter,t,n,i),function(t){return A.indexOf(b(t))>=0}(t)||X(t,e,i)}(t.target,t,n,e):function(t,n,e,i){E(t)||(L(t,e.class_exited),function(t,n,e,i){e.cancel_on_exit&&function(t){return b(t)===l}(t)&&"IMG"===t.tagName&&(J(t),function(t){F(t,(function(t){D(t)})),D(t)}(t),function(t){F(t,(function(t){T(t)})),T(t)}(t),w(t,e.class_loading),x(i,-1),m(t),I(e.callback_cancel,t,n,i))}(t,n,e,i),I(e.callback_exit,t,n,i))}(t.target,t,n,e)}))},nt=function(t){return Array.prototype.slice.call(t)},et=function(t){return t.container.querySelectorAll(t.elements_selector)},it=function(t){return function(t){return b(t)===f}(t)},ot=function(t,n){return function(t){return nt(t).filter(E)}(t||et(n))},rt=function(t,e){var o=c(t);this._settings=o,this.loadingCount=0,function(t,n){i&&!Z(t)&&(n._observer=new IntersectionObserver((function(e){tt(e,t,n)}),function(t){return{root:t.container===document?null:t.container,rootMargin:t.thresholds||t.threshold+"px"}}(t)))}(o,this),function(t,e){n&&window.addEventListener("online",(function(){!function(t,n){var e;(e=et(t),nt(e).filter(it)).forEach((function(n){w(n,t.class_error),m(n)})),n.update()}(t,e)}))}(o,this),this.update(e)};return rt.prototype={update:function(t){var n,o,r=this._settings,a=ot(t,r);z(this,a.length),!e&&i?Z(r)?function(t,n,e){t.forEach((function(t){-1!==Y.indexOf(t.tagName)&&(t.setAttribute("loading","lazy"),function(t,n,e){Q(t,n,e),S(t,n),h(t,_)}(t,n,e))})),z(e,0)}(a,r,this):(o=a,function(t){t.disconnect()}(n=this._observer),function(t,n){n.forEach((function(n){t.observe(n)}))}(n,o)):this.loadAll(a)},destroy:function(){this._observer&&this._observer.disconnect(),et(this._settings).forEach((function(t){delete t.llOriginalAttrs})),delete this._observer,delete this._settings,delete this.loadingCount,delete this.toLoadCount},loadAll:function(t){var n=this,e=this._settings;ot(t,e).forEach((function(t){O(t,n),X(t,e,n)}))}},rt.load=function(t,n){var e=c(n);X(t,e)},rt.resetStatus=function(t){m(t)},n&&function(t,n){if(n)if(n.length)for(var e,i=0;e=n[i];i+=1)s(t,e);else s(t,n)}(rt,window.lazyLoadOptions),rt}));

var lazyLoadInstance = new LazyLoad();

/**
 * RepostPro Schedule Form
 */
RepostPro.ScheduleForm = function() {
    var $form = $(".js-auto-repost-schedule-form");
    var $searchinp = $form.find(":input[name='search']");
    var $searchinp_languages = $form.find(":input[name='search-languages']");
    var query;
    var icons = {};
        icons.hashtag = "mdi mdi-pound";
        icons.location = "mdi mdi-map-marker";
        icons.people = "mdi mdi-instagram";
        icons.collection = "mdi mdi-bookmark";
        icons.people_reels = "mdi mdi-movie";
        icons.hashtag_reels = "mdi mdi-pound";
        icons.music = "mdi mdi-music";
    var target = [];
    var language = [];

    // Get ready tags
    $form.find(".tag").each(function() {
        target.push($(this).data("type") + "-" + $(this).data("id"));
    });

    // Get ready languages
    $form.find(".language").each(function() {
        language.push($(this).data("type") + "-" + $(this).data("id"));
    });

    // Search auto complete for targeting
    $searchinp.devbridgeAutocomplete({
        serviceUrl: $searchinp.data("url"),
        type: "GET",
        dataType: "jsonp",
        minChars: 2,
        deferRequestBy: 200,
        appendTo: $form,
        forceFixPosition: true,
        paramName: "q",
        params: {
            action: "search",
            type: $form.find(":input[name='type']:checked").val(),
        },
        onSearchStart: function() {
            $form.find(".js-search-loading-icon").removeClass('none');
            query = $searchinp.val();
        },
        onSearchComplete: function() {
            $form.find(".js-search-loading-icon").addClass('none');
        },

        transformResult: function(resp) {
            return {
                suggestions: resp.result == 1 ? resp.items : []
            };
        },

        beforeRender: function (container, suggestions) {
            for (var i = 0; i < suggestions.length; i++) {
                var type = $form.find(":input[name='type']:checked").val();
                if (target.indexOf(type + "-" + suggestions[i].data.id) >= 0) {
                    container.find(".autocomplete-suggestion").eq(i).addClass('none')
                }
            }
            setTimeout(function () {
                lazyLoadInstance.update();
            }, 0);
        },

        formatResult: function(suggestion, currentValue){
            var pattern = '(' + currentValue.replace(/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g, "\\$&") + ')';
            var type = $form.find(":input[name='type']:checked").val();

            if (type == "music") {
                return (suggestion.data.img ? "<img src='" + $searchinp.data("url") + "/cors/?media_url=" + suggestion.data.img + "' style='width: 40px;height: 40px;margin: 0 12px 0 0; border-radius: 7px;float:left;border: 1px solid #e6e6e6;'>" : '') + suggestion.value
                    .replace(new RegExp(pattern, 'gi'), '<strong>$1<\/strong>')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/&lt;(\/?strong)&gt;/g, '<$1>') + 
                (suggestion.data.sub ? "<span class='sub'>"+suggestion.data.sub+"<span>" : "");
            } else {
                return (suggestion.data.img ? "<img src='" + $searchinp.data("url") + "/cors/?media_url=" + suggestion.data.img + "' style='width: 40px;height: 40px;margin: 0 12px 0 0; border-radius: 50%;float:left;border: 1px solid #e6e6e6;'>" : '') + suggestion.value
                    .replace(new RegExp(pattern, 'gi'), '<strong>$1<\/strong>')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/&lt;(\/?strong)&gt;/g, '<$1>') + 
                (suggestion.data.sub ? "<span class='sub'>"+suggestion.data.sub+"<span>" : "");
            }
        },

        onSelect: function(suggestion){ 
            $searchinp.val(query);
            var type = $form.find(":input[name='type']:checked").val();

            if (target.indexOf(type+"-"+suggestion.data.id) >= 0) {
                return false;
            }
            
            var $tag = $("<span style='margin: 0px 2px 3px 0px'></span>");
                $tag.addClass("tag pull-left preadd");
                $tag.attr({
                    "data-type": type,
                    "data-id": suggestion.data.id,
                    "data-value": suggestion.value,
                });

                $addit_text = "";
                if (type == "people_reels" || type == "hashtag_reels" || type == "music") {
                    $addit_text = __(" (reels)");
                }

                $tag.text(suggestion.value + $addit_text);

                $tag.prepend("<span class='icon "+icons[type]+"'></span>");
                $tag.append("<span class='mdi mdi-close remove'></span>");

            $tag.appendTo($form.find(".tags"));

            setTimeout(function(){
                $tag.removeClass("preadd");
            }, 50);

            target.push(type+ "-" + suggestion.data.id);
        },

        onHide: function(){
            $("body").find(".autocomplete-suggestion img").attr("src", "");
        }
    });

    // Search auto complete for languages
    $searchinp_languages.devbridgeAutocomplete({
        serviceUrl: $searchinp_languages.data("url"),
        type: "GET",
        dataType: "jsonp",
        minChars: 2,
        deferRequestBy: 200,
        forceFixPosition: true,
        paramName: "q",
        params: {
            action: "search-lang",
            type: "language"
        },
        onSearchStart: function() {
            $form.find(".js-search-loading-icon-languages").removeClass('none');
            query = $searchinp_languages.val(); 
        },
        onSearchComplete: function() {
            $form.find(".js-search-loading-icon-languages").addClass('none');
        },
        transformResult: function(resp) {
            return {
                suggestions: resp.result == 1 ? resp.items : []
            };
        },
        beforeRender: function (container, suggestions) {
            for (var i = 0; i < suggestions.length; i++) {
                var type = "language";
                if (language.indexOf(type + "-" + suggestions[i].data.id) >= 0) {
                    container.find(".autocomplete-suggestion").eq(i).addClass('none')
                }
            }
        },
        formatResult: function(suggestion, currentValue){
            var pattern = '(' + currentValue.replace(/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g, "\\$&") + ')';
            var type = "language";

            return (suggestion.data.img ? "<img src='" + suggestion.data.img + "' style='width: 40px;height: 40px;margin: 0 12px 0 0; border-radius: 50%;float:left;border: 1px solid #e6e6e6;'>" : '') + suggestion.value
                        .replace(new RegExp(pattern, 'gi'), '<strong>$1<\/strong>')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/&lt;(\/?strong)&gt;/g, '<$1>') + 
                    (suggestion.data.sub ? "<span class='sub'>"+suggestion.data.sub+"<span>" : "");
        },
        onSelect: function(suggestion){ 
            $searchinp_languages.val(query);
            var type = "language";

            if (language.indexOf(type+"-"+suggestion.data.id) >= 0) {
                return false;
            }
            
            var $lang = $("<span style='margin: 0px 2px 3px 0px'></span>");
                $lang.addClass("language pull-left preadd");
                $lang.attr({
                    "data-type": type,
                    "data-id": suggestion.data.id,
                    "data-value": suggestion.value,
                });

                $lang.text(suggestion.value);

                $lang.append("<span class='mdi mdi-close remove'></span>");

            $lang.appendTo($form.find(".languages"));

            setTimeout(function(){
                $lang.removeClass("preadd");
            }, 50);

            language.push(type+ "-" + suggestion.data.id);

            var languages_count = $form.find(".languages-count").text();
            $form.find(".languages-count").text(parseInt(languages_count) + 1);
        }
    });

    // Change search source
    $form.find(":input[name='type']").on("change", function() {
        var type = $form.find(":input[name='type']:checked").val();

        $searchinp.autocomplete('setOptions', {
            params: {
                action: "search",
                type: type
            }
        });

        $searchinp.trigger("blur");
        setTimeout(function(){
            $searchinp.trigger("focus");
        }, 200)
        
        // Show/hide "Shuffle saved media in Collections" checkbox
    	if ($(this).is(":input[name='type'][value='collection']:checked")) {
            $form.find(".js-collections-shuffle").css("display", "block");
            $form.find(".js-collections-shuffle").find(":input").prop("disabled", false);
        } else {
            $form.find(".js-collections-shuffle").css("display", "none");
            $form.find(".js-collections-shuffle").find(":input").prop("disabled", true);
        }

        // Show/hide "Shuffle feed items (randomize selection of item for repost)" checkbox
        if ($(this).is(":input[name='type'][value='hashtag']:checked") ||
            $(this).is(":input[name='type'][value='hashtag_reels']:checked") ||
            $(this).is(":input[name='type'][value='music']:checked") ||
            $(this).is(":input[name='type'][value='location']:checked") ||
            $(this).is(":input[name='type'][value='people']:checked") ||
            $(this).is(":input[name='type'][value='people_reels']:checked")) { 
            $form.find(".js-targets-shuffle").css("display", "block");
            $form.find(".js-targets-shuffle").find(":input").prop("disabled", false);
        } else {
            $form.find(".js-targets-shuffle").css("display", "none");
            $form.find(".js-targets-shuffle").find(":input").prop("disabled", true);
        }
    });

    // Remove target
    $form.on("click", ".tag .remove", function() {
        var $tag = $(this).parents(".tag");

        var index = target.indexOf($tag.data("type") + "-" + $tag.data("id"));
        if (index >= 0) {
            target.splice(index, 1);
        }

        $tag.remove();
    });

    // Remove languages
    $form.on("click", ".language .remove", function() {
        var $language = $(this).parents(".language");

        var index = language.indexOf($language.data("type") + "-" + $language.data("id"));
        if (index >= 0) {
            language.splice(index, 1);
        }

        $language.remove(); 

        var languages_count = $form.find(".languages-count").text();
        $form.find(".languages-count").text(parseInt(languages_count) - 1);
    });

    // Daily pause
    $form.find(":input[name='daily-pause']").on("change", function() {
        if ($(this).is(":checked")) {
            $form.find(".js-daily-pause-range").css("opacity", "1");
            $form.find(".js-daily-pause-range").find(":input").prop("disabled", false);
        } else {
            $form.find(".js-daily-pause-range").css("opacity", "0.25");
            $form.find(".js-daily-pause-range").find(":input").prop("disabled", true);
        }
    }).trigger("change");

    // Link verification for Stories
    $form.find(":input[name='share-to-stories-link-value']").on("keyup", function() {
        var link = $(this).val().replaceAll(' ','');
    	var link_check = /^(http|https):\/\/[a-z0-9\u0400-\u04FF]+([\-\.]{1}[a-z0-9\u0400-\u04FF]+)*\.[a-z\u0400-\u04FF]{2,20}(:[0-9]{1,5})?(\/.*)?$/i;
        var link_empty = "";

        if (link !== link_empty) {
            if ((link.substr(0,7) !== "http://") && (link.substr(0,8) !== "https://")) {
                $(this).val("http://"+link);
            	link = $(this).val();
            }
            if (link_check.test(link)) {
                $form.find(".js-link-worked").removeClass("none");
                $form.find(".js-link-not-worked").addClass("none");
                $(this).removeClass("error");
                $form.find(".repost-save").prop("disabled", false);
            } else {
    			$form.find(".js-link-worked").addClass("none");
    			$form.find(".js-link-not-worked").removeClass("none");
    			$(this).addClass("error");
    			$form.find(".repost-save").prop("disabled", true);
            }
        } else {
            $form.find(".js-link-worked").addClass("none");
            $form.find(".js-link-not-worked").addClass("none");
            $(this).removeClass("error");
            $form.find(".repost-save").prop("disabled", false);
        }
    });
         
	// Share to Stories
    $form.find(":input[name='share-to-stories']").on("change", function() {
        if ($(this).is(":checked")) {
            $form.find(":input[name='share-to-stories-media-owner']").css("opacity", "1");
            $form.find(":input[name='share-to-stories-media-owner']").prop("disabled", false);

            $form.find(":input[name='share-to-stories-disable-crop']").css("opacity", "1");
            $form.find(":input[name='share-to-stories-disable-crop']").prop("disabled", false);

            $form.find(":input[name='share-to-stories-hashtag']").css("opacity", "1");
            $form.find(":input[name='share-to-stories-hashtag']").prop("disabled", false);

            // Share to Stories - Hashtag 
            if ($form.find(":input[name='share-to-stories-hashtag']").is(":checked")) {
                $form.find(":input[name='share-to-stories-hashtag-value']").css("opacity", "1");
                $form.find(":input[name='share-to-stories-hashtag-value']").prop("disabled", false);
            } else {
                $form.find(":input[name='share-to-stories-hashtag-value']").css("opacity", "0.25");
                $form.find(":input[name='share-to-stories-hashtag-value']").prop("disabled", true);
            }

            $form.find(":input[name='share-to-stories-main-account']").css("opacity", "1");
            $form.find(":input[name='share-to-stories-main-account']").prop("disabled", false);

            // Share to Stories - Username 
            if ($form.find(":input[name='share-to-stories-main-account']").is(":checked")) {
                $form.find(":input[name='share-to-stories-username-value']").css("opacity", "1");
                $form.find(":input[name='share-to-stories-username-value']").prop("disabled", false);
            } else {
                $form.find(":input[name='share-to-stories-username-value']").css("opacity", "0.25");
                $form.find(":input[name='share-to-stories-username-value']").prop("disabled", true);
            }

            $form.find(":input[name='share-to-stories-link']").css("opacity", "1");
            $form.find(":input[name='share-to-stories-link']").prop("disabled", false);

            // Share to Stories - Link
            if ($form.find(":input[name='share-to-stories-link']").is(":checked")) {
                $form.find(":input[name='share-to-stories-link-value']").css("opacity", "1");
                $form.find(":input[name='share-to-stories-link-value']").prop("disabled", false);
            } else {
                $form.find(":input[name='share-to-stories-link-value']").css("opacity", "0.25");
                $form.find(":input[name='share-to-stories-link-value']").prop("disabled", true);
            }
            
            $form.find(":input[name='share-to-stories-location']").css("opacity", "1");
            $form.find(":input[name='share-to-stories-location']").prop("disabled", false);
        } else {
            $form.find(":input[name='share-to-stories-media-owner']").css("opacity", "0.25");
            $form.find(":input[name='share-to-stories-media-owner']").prop("disabled", true);

            $form.find(":input[name='share-to-stories-disable-crop']").css("opacity", "0.25");
            $form.find(":input[name='share-to-stories-disable-crop']").prop("disabled", true);

            $form.find(":input[name='share-to-stories-hashtag']").css("opacity", "0.25");
            $form.find(":input[name='share-to-stories-hashtag']").prop("disabled",  true);

            $form.find(":input[name='share-to-stories-hashtag-value']").css("opacity", "0.25");
            $form.find(":input[name='share-to-stories-hashtag-value']").prop("disabled", true);

            $form.find(":input[name='share-to-stories-main-account']").css("opacity", "0.25");
            $form.find(":input[name='share-to-stories-main-account']").prop("disabled", true);

            $form.find(":input[name='share-to-stories-username-value']").css("opacity", "0.25");
            $form.find(":input[name='share-to-stories-username-value']").prop("disabled", true);

            $form.find(":input[name='share-to-stories-link']").css("opacity", "0.25");
            $form.find(":input[name='share-to-stories-link']").prop("disabled", true);

            $form.find(":input[name='share-to-stories-link-value']").css("opacity", "0.25");
            $form.find(":input[name='share-to-stories-link-value']").prop("disabled", true);
            
            $form.find(":input[name='share-to-stories-location']").css("opacity", "0.25");
            $form.find(":input[name='share-to-stories-location']").prop("disabled", true);
        }
    }).trigger("change");
    
    // Share to Stories - Hashtag 
    $form.find(":input[name='share-to-stories-hashtag']").on("change", function() {
        if ($(this).is(":checked")) {
            $form.find(":input[name='share-to-stories-hashtag-value']").css("opacity", "1");
            $form.find(":input[name='share-to-stories-hashtag-value']").prop("disabled", false);
        } else {
            $form.find(":input[name='share-to-stories-hashtag-value']").css("opacity", "0.25");
            $form.find(":input[name='share-to-stories-hashtag-value']").prop("disabled", true);
        }
    }).trigger("change");

    // Share to Stories - Username 
    $form.find(":input[name='share-to-stories-main-account']").on("change", function() {
        if ($(this).is(":checked")) {
            $form.find(":input[name='share-to-stories-username-value']").css("opacity", "1");
            $form.find(":input[name='share-to-stories-username-value']").prop("disabled", false);
        } else {
            $form.find(":input[name='share-to-stories-username-value']").css("opacity", "0.25");
            $form.find(":input[name='share-to-stories-username-value']").prop("disabled", true);
        }
    }).trigger("change");

    // Share to Stories - Link 
    $form.find(":input[name='share-to-stories-link']").on("change", function() {
        if ($(this).is(":checked")) {
            $form.find(":input[name='share-to-stories-link-value']").css("opacity", "1");
            $form.find(":input[name='share-to-stories-link-value']").prop("disabled", false);
        } else {
            $form.find(":input[name='share-to-stories-link-value']").css("opacity", "0.25");
            $form.find(":input[name='share-to-stories-link-value']").prop("disabled", true);
        }
    }).trigger("change");

    // Caption input
    // Emoji
    var emoji = $form.find(".arp-caption-input, .arp-first-comment-input").emojioneArea({
        saveEmojisAs      : "unicode", // unicode | shortname | image
        imageType         : "png", // Default image type used by internal CDN
        pickerPosition: 'bottom',
        buttonTitle: __("Use the TAB key to insert emoji faster")
    });

    if (emoji[0] !== undefined) {
        // Emoji area input filter
        emoji[0].emojioneArea.on("drop", function(obj, event) {
            event.preventDefault();
        });

        emoji[0].emojioneArea.on("paste keyup input emojibtn.click", function() {
            $form.find(":input[name='new-comment-input']").val(emoji[0].emojioneArea.getText());
        });
        
        // Select Caption Template for Caption
        $("body").on("click", "#captions-overlay-1 .simple-list-item", function() {
            var caption = $(this).find(":input").val();
            $form.find(".caption")[0].emojioneArea.setText(caption).trigger("keyup");
            $("#captions-overlay-1 .js-close-popup").trigger("click");
        });
        
        // Select Caption Template for First Comment
        $("body").on("click", "#captions-overlay-2 .simple-list-item", function() {
            var caption = $(this).find(":input").val();
            $form.find(".first-comment")[0].emojioneArea.setText(caption).trigger("keyup");
            $("#captions-overlay-2 .js-close-popup").trigger("click");
        });

        // Submit the form
        $form.on("submit", function() {
            $("body").addClass("onprogress");

            var target = [];
            $form.find(".tags .tag").each(function() {
                var t = {};
                    t.type = $(this).data("type");
                    t.id = $(this).data("id").toString();
                    t.value = $(this).data("value");

                target.push(t);
            });

            var language = [];
            $form.find(".languages .language").each(function() {
                var t = {};
                    t.type = $(this).data("type");
                    t.id = $(this).data("id").toString();
                    t.value = $(this).data("value");

                language.push(t);
            });

            $.ajax({
                url: $form.attr("action"),
                type: $form.attr("method"),
                dataType: 'jsonp',
                data: {
                    action: "save",
                    target: JSON.stringify(target),
                    caption: $form.find(".caption")[0].emojioneArea.getText(),
                    first_comment: $form.find(".first-comment")[0].emojioneArea.getText(),
                    speed: $form.find(":input[name='speed']").val(),
                    remove_delay: $form.find(":input[name='remove-delay']").val(),
                    daily_pause: $form.find(":input[name='daily-pause']").is(":checked") ? 1 : 0,
                    daily_pause_from: $form.find(":input[name='daily-pause-from']").val(),
                    daily_pause_to: $form.find(":input[name='daily-pause-to']").val(),
                    is_active: $form.find(":input[name='is_active']").val(),
                    metadata_location: $form.find(":input[name='metadata-location']").is(":checked") ? 1 : 0,
                    metadata_user: $form.find(":input[name='metadata-user']").is(":checked") ? 1 : 0,

                    share_to_stories: $form.find(":input[name='share-to-stories']").is(":checked") ? 1 : 0,
                    share_to_stories_media_owner: $form.find(":input[name='share-to-stories-media-owner']").is(":checked") ? 1 : 0,
                    share_to_stories_location: $form.find(":input[name='share-to-stories-location']").is(":checked") ? 1 : 0,
                    share_to_stories_hashtag: $form.find(":input[name='share-to-stories-hashtag']").is(":checked") ? 1 : 0,
                    share_to_stories_hashtag_value: $form.find(":input[name='share-to-stories-hashtag-value']").val(),
                    share_to_stories_username_value: $form.find(":input[name='share-to-stories-username-value']").val(),
                    share_to_stories_main_account: $form.find(":input[name='share-to-stories-main-account']").is(":checked") ? 1 : 0, 
                    share_to_stories_disable_crop: $form.find(":input[name='share-to-stories-disable-crop']").is(":checked") ? 1 : 0,
                    share_to_stories_link: $form.find(":input[name='share-to-stories-link']").is(":checked") ? 1 : 0,
                    share_to_stories_link_value: $form.find(":input[name='share-to-stories-link-value']").val(),

                    // Reel
                    share_to_facebook_reel: $form.find(":input[name='share-to-facebook-reel']").is(":checked") ? 1 : 0,
                    share_to_timeline_reel: $form.find(":input[name='share-to-timeline-reel']").is(":checked") ? 1 : 0,

                    // Share post & stories to connected Facebook page
                    share_to_facebook_post: $form.find(":input[name='share-to-facebook-post']").is(":checked") ? 1 : 0,
                    share_to_facebook_story: $form.find(":input[name='share-to-facebook-story']").is(":checked") ? 1 : 0,

                    // Language filtration
                    language: JSON.stringify(language),
                    language_detection_notices: $form.find(":input[name='language-detection-notices']").is(":checked") ? 1 : 0,

                    // Media types 
                    photo_posts: $form.find(":input[name='photo-posts']").is(":checked") ? 1 : 0,
                    video_posts: $form.find(":input[name='video-posts']").is(":checked") ? 1 : 0,
                    album_posts: $form.find(":input[name='album-posts']").is(":checked") ? 1 : 0,
                    reels_posts: $form.find(":input[name='reels-posts']").is(":checked") ? 1 : 0,

                    // Caption Filtration
                    caption_filtration: $form.find(":input[name='caption-filtration']").val(),

                    // Users Blacklists
                    ub_filtration: $form.find(":input[name='ub-filtration']").val(),

                    // Find & Replace for original post caption
                    find_and_replace: $form.find(":input[name='find-and-replace']").val(),

                    // Find & Tag on image for original post caption
                    find_and_tag: $form.find(":input[name='find-and-tag']").is(":checked") ? 1 : 0,
                    find_and_tag_blacklist: $form.find(":input[name='find-and-tag-blacklist']").val(),

                    // Custom usertags for post
                    custom_usertags_post: $form.find(":input[name='custom-usertags-post']").val(),

                    // Custom repost time
                    custom_repost_time: $form.find(":input[name='custom-repost-time']").val(),

                    // Custom proxy 
                    custom_proxy: $form.find(":input[name='custom-proxy']").val(),

                    // Debug mode
                    debug_mode: $form.find(":input[name='debug-mode']").is(":checked") ? 1 : 0,

                    // Filtration
                    filtration_profile_picture: $form.find(":input[name='filtration-profile-picture']").is(":checked") ? 1 : 0,
                    filtration_min_comments: $form.find(":input[name='filtration-min-comments']").val(),
                    filtration_max_comments: $form.find(":input[name='filtration-max-comments']").val(),
                    filtration_min_likes: $form.find(":input[name='filtration-min-likes']").val(),
                    filtration_max_likes: $form.find(":input[name='filtration-max-likes']").val(),
                    filtration_min_views: $form.find(":input[name='filtration-min-views']").val(),
                    filtration_max_views: $form.find(":input[name='filtration-max-views']").val(),
                    filtration_min_plays: $form.find(":input[name='filtration-min-plays']").val(),
                    filtration_max_plays: $form.find(":input[name='filtration-max-plays']").val(),
    
                    collections_shuffle: $form.find(":input[name='collections-shuffle']").is(":checked") ? 1 : 0,
                    targets_shuffle: $form.find(":input[name='targets-shuffle']").is(":checked") ? 1 : 0,
                },
                error: function() {
                    $("body").removeClass("onprogress");
                    NextPost.DisplayFormResult($form, "error", __("Oops! An error occured. Please try again later!"));
                },

                success: function(resp) {
                    if (resp.result == 1) {
                        NextPost.DisplayFormResult($form, "success", resp.msg);

                        var active_schedule = $(".aside-list-item.active");

                        if ($form.find(":input[name='is_active']").val() == 1) {
                            active_schedule.find("span.status").replaceWith("<span class='status color-green'><span class='mdi mdi-circle mr-2'></span>" + __('Active') + "</span>");
                        } else {
                            active_schedule.find("span.status").replaceWith("<span class='status'><span class='mdi mdi-circle-outline mr-2'></span>" + __('Deactive') + "</span>");
                        }

                        if (resp.custom_usertags_post) {
                            if (resp.custom_usertags_post.length > 0) {
                                $form.find("#custom-usertags-post").val(resp.custom_usertags_post);
                            }
                        }

                        if (resp.custom_usertags_array) {
                            if (resp.custom_usertags_array.length > 0) {
                                var cu_preview_data = "";
                                resp.custom_usertags_array.forEach(c_usertag => {
                                    cu_preview_data = cu_preview_data + "@" + c_usertag["username"] + " (ID: " + c_usertag["user_id"] + ") x=" + c_usertag["position"][0] + " y=" + c_usertag["position"][1] + "<br>";
                                });
                                $cu_preview = $form.find(".custom-usertags-preview").replaceWith('<div class="mb-5 debug-data custom-usertags-preview">' + cu_preview_data + '</div>');
                            }
                        }

                        if (resp.repost_time) {
                            if (resp.repost_time.length > 0) {
                                var crt_preview_data = resp.repost_time.join(' | ');
                                $crt_preview = $form.find(".custom-repost-time-preview").replaceWith('<div class="mb-5 mt-5 debug-data custom-repost-time-preview">' + crt_preview_data + '</div>');
                            }
                        }
                    } else {
                        NextPost.DisplayFormResult($form, "error", resp.msg);
                    }

                    $("body").removeClass("onprogress");
                }
            });

            return false;
        });
    }

    // Alert notice
    specialAlertResponse = function(resp = null) {
        $.alert({
            title: resp ? ( resp.title ? resp.title: __("Oops!") )  : __("Oops!"),
            content: resp ? ( resp.msg ? resp.msg: __("An error occured. Please try again later!") )  : __("An error occured. Please try again later!"),
            theme: 'modern',
            buttons: {
                confirm: {
                    text: __("Close"), 
                    btnClass: "small button btn-red",
                    keys: ['enter']
                }
            },
            draggable: false,
            closeIcon: true,
            icon: 'sli sli-close',
            type: 'red'
        });
    }

    $("body").off("click", ".js-copy-task-settings-repost-pro").on("click", ".js-copy-task-settings-repost-pro", function() {
        var _this = $(this);
        var copy_button = _this.html();
        var url = _this.data("url");

        if ($(this).data("id") == $(".aside-list-item.active").data("id")) {
            $.ajax({
                url: url,
                type: 'POST',
                dataType: 'jsonp',
                data: {
                  action: "copy-task-settings"
                },
                error: function() {
                    specialAlertResponse();
                },
                success: function(resp) {
                    if (resp.result !== 1) {
                        specialAlertResponse(resp);
                    } else {
                        if (resp.target || 
                            resp.caption ||
                            resp.first_comment ||
                            resp.remove_delay ||
                            resp.speed ||
                            resp.metadata_location ||
                            resp.metadata_user ||
                            resp.collections_shuffle ||
                            resp.data || 
                            resp.daily_pause || 
                            resp.daily_pause_from || 
                            resp.daily_pause_to ||
                            resp.is_active) {
                            var copyhelper = document.createElement("input");
                            document.body.appendChild(copyhelper);
                            copyhelper.value = JSON.stringify({
                                "target": resp.target,
                                "caption": resp.caption,
                                "first_comment": resp.first_comment,
                                "remove_delay": resp.remove_delay,
                                "speed": resp.speed,
                                "metadata_location": resp.metadata_location,
                                "metadata_user": resp.metadata_user,
                                "collections_shuffle": resp.collections_shuffle,
                                "data": resp.data,
                                "daily_pause": resp.daily_pause,
                                "daily_pause_from": resp.daily_pause_from,
                                "daily_pause_to": resp.daily_pause_to,
                                "is_active": resp.is_active
                            });
                            copyhelper.select();
                            document.execCommand("copy");
                            document.body.removeChild(copyhelper);

                            _this.html(__("Settings copied to clipboard"));
                            _this.removeClass("js-copy-task-settings");
                            _this.addClass("reactions-copyhelper-done");
                            data_copied_animation_done = setTimeout(function (){
                                _this.html(copy_button);
                                _this.addClass("js-copy-task-settings");
                                _this.removeClass("reactions-copyhelper-done");
                            }, 2000);
                        } else {
                            _this.html(__("Task settings is empty"));
                            _this.removeClass("js-copy-task-settings");
                            _this.addClass("reactions-copyhelper-error");
                            data_copied_animation_error = setTimeout(function (){
                                _this.html(copy_button);
                                _this.addClass("js-copy-task-settings");
                                _this.removeClass("reactions-copyhelper-error");
                            }, 2000);
                        }
                    }
                }
            });
        }
    });

    var insert_data_popup = $("#insert-data-popup-repost-pro");
    insert_data_popup.off("click", ".js-insert-task-settings-repost-pro").on("click", ".js-insert-task-settings-repost-pro", function() {
        var _this = $(this);
        var url = _this.data("url");
        var insert_data_textarea = insert_data_popup.find("textarea.insert-data-repost-pro");
        var insert_data = insert_data_textarea.val();

        if ($(this).data("id") == $(".aside-list-item.active").data("id")) {
            if (insert_data) {
                var insert = JSON.parse(insert_data);
                if (insert.target || 
                    insert.caption ||
                    insert.first_comment ||
                    insert.remove_delay ||
                    insert.speed ||
                    insert.metadata_location ||
                    insert.metadata_user ||
                    insert.collections_shuffle ||
                    insert.data || 
                    insert.daily_pause || 
                    insert.daily_pause_from || 
                    insert.daily_pause_to ||
                    insert.is_active) {
                    $.ajax({
                        url: url,
                        type: 'POST',
                        dataType: 'jsonp',
                        data: {
                            action: "insert-task-settings",
                            insert: insert
                        },
                        error: function() {
                            specialAlertResponse();
                        },
                        success: function(resp) {
                            if (resp.result !== 1) {
                                specialAlertResponse(resp);
                            } else {
                                insert_data_popup.modal('hide');
                                window.location.href = resp.redirect;
                            }
                        }
                    });
                } else {
                    specialAlertResponse({
                        msg: __("Clipboard data is invalid")
                    });
                }
            } else {
                specialAlertResponse({
                    msg: __("Clipboard is empty")
                });
            }
        }
    });

    // All
    var insert_data_popup2 = $("#insert-data-popup-repost-pro");
    insert_data_popup2.off("click", ".js-insert-task-settings-all-repost-pro").on("click", ".js-insert-task-settings-all-repost-pro", function() {
        var _this = $(this);
        var url = _this.data("url");
        var insert_data_textarea = insert_data_popup2.find("textarea.insert-data-repost-pro");
        var insert_data = insert_data_textarea.val();

        if ($(this).data("id") == $(".aside-list-item.active").data("id")) {
            if (insert_data) {
                var insert = JSON.parse(insert_data);
                if (insert.target || 
                    insert.caption ||
                    insert.first_comment ||
                    insert.remove_delay ||
                    insert.speed ||
                    insert.metadata_location ||
                    insert.metadata_user ||
                    insert.collections_shuffle ||
                    insert.data || 
                    insert.daily_pause || 
                    insert.daily_pause_from || 
                    insert.daily_pause_to ||
                    insert.is_active) {
                    $.ajax({
                        url: url,
                        type: 'POST',
                        dataType: 'jsonp',
                        data: {
                            action: "insert-task-settings-all",
                            insert: insert
                        },
                        error: function() {
                            specialAlertResponse();
                        },
                        success: function(resp) {
                            if (resp.result !== 1) {
                                specialAlertResponse(resp);
                            } else {
                                insert_data_popup.modal('hide');
                                window.location.href = resp.redirect;
                            }
                        }
                    });
                } else {
                    specialAlertResponse({
                        msg: __("Clipboard data is invalid")
                    });
                }
            } else {
                specialAlertResponse({
                    msg: __("Clipboard is empty")
                });
            }
        }
    });
}

/**
 * RepostPro Index
 */
RepostPro.Index = function() {
    $(document).ajaxComplete(function(event, xhr, settings) {
        var rx = new RegExp("(repost-pro\/[0-9]+(\/|\/log)?)$");
        if (rx.test(settings.url)) {
            RepostPro.ScheduleForm();
            NextPost.Tooltip();
            lazyLoadInstance.update();
            
            // Update selected schedule speed
            var active_schedule = $(".aside-list-item.active");
            $.ajax({
                url: active_schedule.data("url"),
                type: 'POST',
                dataType: 'jsonp',
                data: {
                    action: "update_data",
                    id: active_schedule.data("id")
                },
                success: function(resp) {
                    if (resp.result == 1) {
                        if (resp.speed) {
                            active_schedule.find("span.speed-value").replaceWith("<span class='speed-value'>" + resp.speed + "</span>");
                        }
                        if (resp.is_active && resp.schedule_date_value) {
                            active_schedule.find("span.schedule-date-value").replaceWith("<span class='schedule-date-value'>" + resp.schedule_date_value + "</span>");
                        }
                        if (resp.is_active) {
                            active_schedule.find("span.status").replaceWith("<span class='status color-green'><span class='mdi mdi-circle mr-2'></span>" + __('Active') + "</span>");
                        } else {
                            active_schedule.find("span.status").replaceWith("<span class='status'><span class='mdi mdi-circle-outline mr-2'></span>"  + __('Deactive') + "</span>");
                        }
                    }
                }
            });
        }
    });

    window.loadmore.success = function($item) {
        NextPost.Tooltip();
        lazyLoadInstance.update();
    }
}

/**
 * Show only active tasks
 */
var search_timer;
var search_xhr;
var $form = $(".skeleton-aside .search-box");

$("body").on("click", "a.js-only-active", function() {
    var _this = $(this);
    var only_active_inp = $form.find(":input[name='only_active']");
    var search_query = $form.find(":input[name='q']");

    if (only_active_inp.val() == 0) {
        only_active_inp.val(1);
        _this.removeClass('button--light-outline');
        _this.addClass('active');
    } else {
        only_active_inp.val(0)
        _this.addClass('button--light-outline');
        _this.removeClass('active');
    }

    if (search_xhr) {
        // Abort previous ajax request
        search_xhr.abort();
    }

    if (search_timer) {
        clearTimeout(search_timer);
    }

    data = $.param({
        only_active: (only_active_inp.val() == 1) ? "yes" : "no"
    });

    if (search_query.val() != '') {
        data += '&' + $.param({
            q: search_query.val(),
        });
    }

    var duration = 200;
    search_timer = setTimeout(function(){
        search_query.addClass("onprogress");

        $.ajax({
            url: $form.attr("action"),
            type: $form.attr("method"),
            dataType: 'html',
            data: data,
            complete: function() {
                search_query.removeClass('onprogress');
            },
            success: function(resp) {
                $resp = $(resp);

                if ($resp.find(".skeleton-aside .js-search-results").length == 1) {
                    $(".skeleton-aside .js-search-results")
                        .html($resp.find(".skeleton-aside .js-search-results").html());
                }
            }
        });
    }, duration);
});

RepostPro.DeleteRepost = function() {
    $("body").on("click", "a.js-delete-repost", function() {
        var _this = $(this);
        var active_schedule = $(".aside-list-item.active");
        var active_repost = $("body").find(".arp-log-list-item[data-repost-id='" + _this.data("repost-id") + "']");
        active_repost.addClass("onprogress");
        $.ajax({
            url: active_schedule.data("url"),
            type: 'POST',
            dataType: 'jsonp',
            data: {
                action: "delete_repost",
                repost_id: _this.data("repost-id"),
                account_id: _this.data("account-id")
            },
            error: function(resp) {
                active_repost.removeClass("onprogress");
                NextPost.Alert({ 
                    title: resp.title ? resp.title : __("Oops..."),
                    content: resp.msg ? resp.msg : __("An error occured. Please try again later!"),
                    confirmText: __("Close"),

                    confirm: function() {
                        if (resp.redirect) {
                            window.location.href = resp.redirect; 
                        }
                    }
                });
            },
            success: function(resp) {
                active_repost.removeClass("onprogress");
                if (resp.result == 1) {
                    active_repost.remove();
                }
            }
        });
    });
}

RepostPro.UpdateStatistics = function() {
    function numberWithSpaces(x) {
        return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, " ");
    }
    $("body").on("click", ".js-repost-pro-statistics-update", function() {
        var _this = $(this);
        var analytics_box = _this.parent(".repost-pro-last-analytics-update");
        analytics_box.addClass("onprogress");
        $.ajax({
            url: _this.data("action-url"),
            type: 'POST',
            dataType: 'jsonp',
            data: {
                action: "update-statistics"
            },
            error: function(resp) {
                analytics_box.removeClass("onprogress");
                NextPost.Alert({ 
                    title: resp.title ? resp.title : __("Oops..."),
                    content: resp.msg ? resp.msg : __("An error occured. Please try again later!"),
                    confirmText: __("Close"),
                    confirm: function() {
                        if (resp.redirect) {
                            window.location.href = resp.redirect; 
                        }
                    }
                });
            },
            success: function(resp) {
                analytics_box.removeClass("onprogress");
                if (resp.last_analytics_update) {
                    $("body").find(".last-analytics-update-timestamp").html(resp.last_analytics_update);
                }
                if (resp.analytics) {
                    $.each(resp.analytics, function(index, element) {
                        var data_analytics = $("body").find(".data-analytics[data-media-code='" + index + "']");
                        if (element.likes_count) {
                            data_analytics.find(".rp-likes-count").html(numberWithSpaces(element.likes_count));
                            data_analytics.find(".rp-likes-box").removeClass("none");
                        }
                        if (element.comments_count) {
                            data_analytics.find(".rp-comments-count").html(numberWithSpaces(element.comments_count));
                            data_analytics.find(".rp-comments-box").removeClass("none");
                        }
                        if (element.views_count) {
                            data_analytics.find(".rp-views-count").html(numberWithSpaces(element.views_count));
                            data_analytics.find(".rp-views-box").removeClass("none");
                        }
                        if (element.er_post) {
                            if (element.er_post && element.er_post !== "0.00" && element.er_post !== "0.01") {
                                data_analytics.find(".rp-er-post-value").html(element.er_post);
                                if (element.er_post_is_good) {
                                    data_analytics.find(".er-post-box").addClass("good-er-post");
                                    data_analytics.find(".repost-pro-logs-er-post").addClass("good-er-post");
                                } else {
                                    data_analytics.find(".er-post-box").removeClass("good-er-post");
                                    data_analytics.find(".repost-pro-logs-er-post").removeClass("good-er-post");
                                }
                                data_analytics.find(".er-post-box").removeClass("none");
                            }
                        }
                    });
                }
            }
        });
    });
}

RepostPro.LogActions = function() {
    /**
     * Select a log
     */
    $("body").off("click", ".js-repost-pro-select-log").on("click", ".js-repost-pro-select-log", function() {
        var _this = $(this);
        var log_id = _this.attr("data-log-id");
        var list_item = $("body").find(".arp-log-list-item[data-repost-id=" + log_id + "]");
        var analytics_box = _this.parent(".repost-pro-last-analytics-update");
        
        analytics_box.addClass("onprogress");

        $.ajax({
            url: _this.attr("data-action-url"),
            type: "POST",
            dataType: 'jsonp',
            data: {
                action: "select-log",
                id: log_id,
                log_selected: list_item.hasClass("log-selected") ? 1 : 0
            },
            error: function() {
                analytics_box.removeClass("onprogress");
                NextPost.Alert({ 
                    title:  __("Oops..."),
                    content:  __("An error occured. Please try again later!"),
                    confirmText: __("Close")
                });
            },
            success: function(resp) {
                analytics_box.removeClass("onprogress");
                if (resp.result == 1) {
                    if (list_item.hasClass("log-selected")) {
                        _this.addClass("mdi-checkbox-blank-circle-outline");
                        _this.removeClass("mdi-checkbox-marked-circle-outline");
                        list_item.removeClass("log-selected");
                    } else {
                        _this.removeClass("mdi-checkbox-blank-circle-outline");
                        _this.addClass("mdi-checkbox-marked-circle-outline");
                        list_item.addClass("log-selected");
                    }
                } else {
                    NextPost.Alert({ 
                        title: __("Oops..."),
                        content: resp.msg,
                        confirmText: __("Close"),

                        confirm: function() {
                            if (resp.redirect) {
                                window.location.href = resp.redirect; 
                            }
                        }
                    });
                }
            }
        });
    });

    /**
     * Bulk select all proxies
     */
    $("body").off("click", ".js-repost-pro-bulk-select").on("click", ".js-repost-pro-bulk-select", function() {
        var _this = $(this);
        var list_item = $("body").find(".arp-log-list-item");
        var analytics_box = _this.parent(".repost-pro-last-analytics-update");

        analytics_box.addClass("onprogress");

        $.ajax({
            url: _this.attr("data-action-url"),  
            type: "POST",
            dataType: 'jsonp',
            data: {
                action: "bulk-select",
            }, 
            error: function() {
                analytics_box.removeClass("onprogress");
                NextPost.Alert({ 
                    title:  __("Oops..."),
                    content:  __("An error occured. Please try again later!"),
                    confirmText: __("Close")
                });
            },
            success: function(resp) {
                analytics_box.removeClass("onprogress");
                if (resp.result == 1) {
                    list_item.find(".js-repost-pro-select-log").removeClass("mdi-checkbox-blank-circle-outline");
                    list_item.find(".js-repost-pro-select-log").addClass("mdi-checkbox-marked-circle-outline");
                    list_item.addClass("log-selected");
                    if (resp.redirect) {
                        window.location.href = resp.redirect; 
                    }
                } else {
                    NextPost.Alert({ 
                        title: __("Oops..."),
                        content: resp.msg,
                        confirmText: __("Close"),

                        confirm: function() {
                            if (resp.redirect) {
                                window.location.href = resp.redirect; 
                            }
                        }
                    });
                }
            }
        });
    });

    /**
     * Bulk unselect all proxies
     */
    $("body").off("click", ".js-repost-pro-bulk-unselect").on("click", ".js-repost-pro-bulk-unselect", function() {
        var _this = $(this);
        var list_item = $("body").find(".arp-log-list-item");
        var analytics_box = _this.parent(".repost-pro-last-analytics-update");
        
        analytics_box.addClass("onprogress");

        $.ajax({
            url: _this.attr("data-action-url"),  
            type: "POST",
            dataType: 'jsonp',
            data: {
                action: "bulk-unselect",
            }, 
            error: function() {
                analytics_box.removeClass("onprogress");
                NextPost.Alert({ 
                    title:  __("Oops..."),
                    content:  __("An error occured. Please try again later!"),
                    confirmText: __("Close")
                });
            },
            success: function(resp) {
                analytics_box.removeClass("onprogress");
                if (resp.result == 1) {
                    list_item.find(".js-repost-pro-select-log").addClass("mdi-checkbox-blank-circle-outline");
                    list_item.find(".js-repost-pro-select-log").removeClass("mdi-checkbox-marked-circle-outline");
                    list_item.removeClass("log-selected");
                    if (resp.redirect) {
                        window.location.href = resp.redirect; 
                    }
                } else {
                    NextPost.Alert({ 
                        title: __("Oops..."),
                        content: resp.msg,
                        confirmText: __("Close"),

                        confirm: function() {
                            if (resp.redirect) {
                                window.location.href = resp.redirect; 
                            }
                        }
                    });
                }
            }
        });
    });

    /**
     * Bulk remove proxies
     */
    $("body").off("click", ".js-repost-pro-bulk-delete").on("click", ".js-repost-pro-bulk-delete", function() {
        var _this = $(this);
        var analytics_box = _this.parent(".repost-pro-last-analytics-update");
        
        analytics_box.addClass("onprogress");
        
        $.ajax({
            url: _this.attr("data-action-url"),  
            type: "POST",
            dataType: 'jsonp',
            data: {
                action: "bulk-remove",
            }, 
            error: function() {
                analytics_box.removeClass("onprogress");
                NextPost.Alert({ 
                    title:  __("Oops..."),
                    content:  __("An error occured. Please try again later!"),
                    confirmText: __("Close")
                });
            },
            success: function(resp) {
                analytics_box.removeClass("onprogress");
                if (resp.result == 1) {
                    if (resp.deleted_logs.length > 0) {
                        resp.deleted_logs.forEach(element => {
                            $("body").find(".arp-log-list-item[data-repost-id=" + element + "]").remove();
                        });
                    }
                    if (resp.redirect) {
                        window.location.href = resp.redirect; 
                    }
                } else {
                    NextPost.Alert({ 
                        title: __("Oops..."),
                        content: resp.msg,
                        confirmText: __("Close"),

                        confirm: function() {
                            if (resp.redirect) {
                                window.location.href = resp.redirect; 
                            }
                        }
                    });
                }
            }
        });
    });
}