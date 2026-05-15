/**
 * @package Helix Ultimate Framework
 * @author JoomShaper https://www.joomshaper.com
 * @copyright Copyright (c) 2010 - 2018 JoomShaper
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or Later
*/

jQuery(function ($) {

    // Stikcy Header
    if ($('body').hasClass('sticky-header')) {
        var header = $('#sp-header');

        if($('#sp-header').length) {
            var headerHeight = header.outerHeight();
            var stickyHeaderTop = header.offset().top;
            header.before('<div class="nav-placeholder"></div>');
            var stickyHeader = function () {
                var scrollTop = $(window).scrollTop();
                if (scrollTop > stickyHeaderTop) {
                    header.addClass('header-sticky');
                    $('.nav-placeholder').height(headerHeight);
                } else {
                    if (header.hasClass('header-sticky')) {
                        header.removeClass('header-sticky');
                        $('.nav-placeholder').height('inherit');
                    }
                }
            };
            stickyHeader();
            $(window).scroll(function () {
                stickyHeader();
            });
        }

        if ($('body').hasClass('layout-boxed')) {
            var windowWidth = header.parent().outerWidth();
            header.css({"max-width": windowWidth, "left": "auto"});
        }
    }

    // go to top
    $(window).scroll(function () {
        if ($(this).scrollTop() > 100) {
            $('.sp-scroll-up').fadeIn();
        } else {
            $('.sp-scroll-up').fadeOut(400);
        }
    });

    $('.sp-scroll-up').click(function () {
        $("html, body").animate({
            scrollTop: 0
        }, 600);
        return false;
    });

    // Preloader
    $(window).on('load', function () {
        $('.sp-preloader').fadeOut(500, function() {
            $(this).remove();
        });
    });

    //mega menu
    $('.sp-megamenu-wrapper').parent().parent().css('position', 'static').parent().css('position', 'relative');
    $('.sp-menu-full').each(function () {
        $(this).parent().addClass('menu-justify');
    });

    // Offcanvs
    $('#offcanvas-toggler').on('click', function (event) {
        event.preventDefault();
        $('.offcanvas-init').addClass('offcanvas-active');
    });

    $('.close-offcanvas, .offcanvas-overlay').on('click', function (event) {
        event.preventDefault();
        $('.offcanvas-init').removeClass('offcanvas-active');
    });

    $(document).on('click', '.offcanvas-inner .menu-toggler', function(event){
        event.preventDefault();
        $(this).closest('.menu-parent').toggleClass('menu-parent-open').find('>.menu-child').slideToggle(400);
    });

    // Tooltip
	var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"], .hasTooltip'));
	var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
		return new bootstrap.Tooltip(tooltipTriggerEl,{
			html: true
		  });
	});

	// Popover
	var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
	var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
		return new bootstrap.Popover(popoverTriggerEl);
	});

    // Article Ajax voting
    $('.article-ratings .rating-star').on('click', function (event) {
        event.preventDefault();
        var $parent = $(this).closest('.article-ratings');

        var request = {
            'option': 'com_ajax',
            'template': template,
            'action': 'rating',
            'rating': $(this).data('number'),
            'article_id': $parent.data('id'),
            'format': 'json'
        };

        $.ajax({
            type: 'POST',
            data: request,
            beforeSend: function () {
                $parent.find('.fa-spinner').show();
            },
            success: function (response) {
                var data = $.parseJSON(response);
                $parent.find('.ratings-count').text(data.message);
                $parent.find('.fa-spinner').hide();

                if(data.status)
                {
                    $parent.find('.rating-symbol').html(data.ratings)
                }

                setTimeout(function(){
                    $parent.find('.ratings-count').text('(' + data.rating_count + ')')
                }, 3000);
            }
        });
    });

    //  Cookie consent
    $('.sp-cookie-allow').on('click', function(event) {
        event.preventDefault();

        var date = new Date();
        date.setTime(date.getTime() + (30 * 24 * 60 * 60 * 1000));
        var expires = "; expires=" + date.toGMTString();
        document.cookie = "spcookie_status=ok" + expires + "; path=/";

        $(this).closest('.sp-cookie-consent').fadeOut();
    });

    //Template related JS
    $('.sppb-addon-articles .sppb-article-introtext').each(function(){
        let introText = $(this).find('p');
        $(this).html(introText);
    })

    if($('.main-login-page').length>0){
        $('input.validate-username').attr('placeholder', "Username")
        $('input.validate-password').attr('placeholder', "Password")
    }
    if ($('#member-registration').length>0){
        $('#jform_name').attr('placeholder', 'Full Name')
        $('#jform_username').attr('placeholder', 'Username')
        $('#jform_password1').attr('placeholder', 'Password')
        $('#jform_password2').attr('placeholder', 'Password Again')
        $('#jform_email1').attr('placeholder', 'Email')
        $('#jform_email2').attr('placeholder', 'Email Again')
    }
    if ($("ul.pagination").length>0){
        $('.page-link.previous').closest('.page-item').addClass('previous');
        $('.page-link.next').closest('.page-item').addClass('next');
        $('.page-link.first').closest('.page-item').addClass('first');
        $('.page-link.last').closest('.page-item').addClass('last');
    }
    // Select
    if ($(".my-select").length > 0) {
        $(document).on('click', function (e) {
            var selector = $('.my-select');
            if (!selector.is(e.target) && selector.has(e.target).length === 0) {
                selector.find('ul').slideUp();
            }
        });

        $('select.my-select').each(function (event) {
            $(this).hide();
            var $self = $(this);
            var spselect = '<div class="my-select">';
            spselect += '<div class="my-select-result">';
            spselect += '<span class="my-select-text">' + $self.find('option:selected').text() + '</span>';
            spselect += ' <i class="fa fa-caret-down"></i>';
            spselect += '</div>';
            spselect += '<ul class="my-select-dropdown">';

            $self.children().each(function (event) {
                if ($self.val() == $(this).val()) {
                    spselect += '<li class="active" data-val="' + $(this).val() + '">' + $(this).text() + '</li>';
                } else {
                    spselect += '<li data-val="' + $(this).val() + '">' + $(this).text() + '</li>';
                }
            });

            spselect += '</ul>';
            spselect += '</div>';
            $(this).after($(spselect));
        });

        $(document).on('click', '.my-select', function (event) {
            $('.my-select').not(this).find('ul').slideUp();
            $(this).find('ul').slideToggle();
        });

        $(document).on('click', '.my-select ul li', function (event) {
            var $select = $(this).closest('.my-select').prev('select');
            $(this).parent().prev('.my-select-result').find('span').html($(this).text());
            $(this).parent().find('.active').removeClass('active');
            $(this).addClass('active');
            $select.val($(this).data('val'));
            $select.change();
        });
    }
    // End Select
});

// EN nav translation fix (FaLang fallback)
(function(){
  if(window.location.pathname.indexOf('/en/')<0)return;
  var T={
    'Compr\u00e9hension Orale':'Listening Comprehension',
    'Compr\u00e9hension \u00c9crite':'Reading Comprehension',
    'Expression Orale':'Speaking',
    'Expression \u00c9crite':'Writing',
    'Cours de fran\u00e7ais pour adultes':'French Courses for Adults',
    'Cours de fran\u00e7ais pour enfants':'French Courses for Children',
    'Camps th\u00e9matiques':'Themed Camps',
    'Pr\u00e9paration tests de langue':'Language Test Preparation',
    'Fran\u00e7ais Professionnel \u2013 Sant\u00e9':'Professional French \u2013 Healthcare',
    'Inscription aux Cours':'Course Registration',
    'Tableau des prix':'Price List',
    'Calendrier des sessions':'Session Calendar',
    'S\u2019inscrire \u00e0 un examen':'Register for an Exam',
    'Nos partenaires':'Our Partners',
    'Nos \u00c9v\u00e9nements !':'Our Events!',
    'Entra\u00eenement au TCF (Canada/Qu\u00e9bec)':'TCF Canada & Qu\u00e9bec Practice',
    'Entra\u00eenement au TEF (Canada/Qu\u00e9bec)':'TEF Canada & TEFAQ Practice',
    'Pr\u00e9paration TCF Canada':'TCF Canada Preparation',
    'Inscription aux Tests de Langue':'Language Test Registration',

    'Test officiel de fran\u00e7ais pour l\u2019immigration et la citoyennet\u00e9 au Canada':'Official French test for immigration and Canadian citizenship.',
    'Test officiel de fran\u00e7ais pour l\u2019immigration et la citoyennet\u00e9 canadienne':'Official French test for immigration and Canadian citizenship.',
    'Test officiel de fran\u00e7ais pour l\u2019immigration au Qu\u00e9bec':'Official French test for immigration to Qu\u00e9bec.',
    'Foire aux questions \u2013 Test de fran\u00e7ais pour l\u2019immigration et la citoyennet\u00e9 canadienne':'Frequently Asked Questions \u2013 French tests for immigration.',
    'Niveau A1 \u2013 Premiers pas en fran\u00e7ais':'Level A1 \u2013 First steps in French',
    'Pr\u00e9paration tests de langue':'Language Test Preparation',
    'Inscriptions ferm\u00e9es':'Registrations closed',
    'Nos \u00c9v\u00e9nements !':'Our Events!',
    'Nos Blogs':'Our Blog',
    'Cond[ui]tion G\u00e9n\u00e9rales de ventes':'General Terms and Conditions'
  };
  function walk(n){
    if(n.nodeType===3){var t=n.textContent.trim();if(T[t])n.textContent=n.textContent.replace(t,T[t]);}
    else if(n.nodeType===1&&n.tagName!=='SCRIPT'&&n.tagName!=='STYLE')n.childNodes.forEach(walk);
  }
  document.addEventListener('DOMContentLoaded',function(){walk(document.body);});
})();

// AFK: nettoyer les URLs du commutateur de langue sur toutes les pages RSEvents Pro
// Supprime les segments de vue jour/mois/an pour revenir à la vue calendrier de base
(function(){
  function cleanRseUrls(){
    var pattern = /\/(daily|monthly|yearly|day|jour|jours|COM_RSEVENTSPRO_CALENDAR_DAY_SEF|COM_RSEVENTSPRO_CALENDAR_MONTH_SEF|COM_RSEVENTSPRO_CALENDAR_YEAR_SEF)(\/[^?#]*)?/g;
    document.querySelectorAll('a[href]').forEach(function(a){
      if(pattern.test(a.href)){
        a.href = a.href.replace(pattern, '');
      }
      pattern.lastIndex = 0;
    });
  }
  document.addEventListener('DOMContentLoaded', cleanRseUrls);
})();
