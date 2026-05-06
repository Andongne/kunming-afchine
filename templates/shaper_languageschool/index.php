<?php
/**
 * @package Helix Ultimate Framework
 * @author JoomShaper https://www.joomshaper.com
 * @copyright Copyright (c) 2010 - 2021 JoomShaper
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or Later
*/

defined ('_JEXEC') or die();

use HelixUltimate\Framework\Core\HelixUltimate;
use HelixUltimate\Framework\Platform\Helper;
use HelixUltimate\Framework\System\JoomlaBridge;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;



$app = Factory::getApplication();
$this->setHtml5(true);

/**
 * Load the framework bootstrap file for enabling the HelixUltimate\Framework namespacing.
 *
 * @since	2.0.0
 */
$bootstrap_path = JPATH_PLUGINS . '/system/helixultimate/bootstrap.php';

if (file_exists($bootstrap_path))
{
	require_once $bootstrap_path;
}
else
{
	die('Install and activate <a target="_blank" rel="noopener noreferrer" href="https://www.joomshaper.com/helix">Helix Ultimate Framework</a>.');
}

/**
 * Get the theme instance from Helix framework.
 *
 * @var		$theme		The theme object from the class HelixUltimate.
 * @since	1.0.0
 */
$theme = new HelixUltimate;
$template = Helper::loadTemplateData();
$this->params = $template->params;


/** Load needed data for javascript */
Helper::flushSettingsDataToJs();

// Coming Soon
if (!\is_null($this->params->get('comingsoon', null)))
{
	header("Location: " . Route::_(Uri::root(true) . "/index.php?templateStyle={$template->id}&tmpl=comingsoon", false));
	exit();
}

$custom_style = $this->params->get('custom_style');
$preset = $this->params->get('preset');

if($custom_style || !$preset)
{
    $scssVars = array(
        'preset' => 'default',
        'text_color' => $this->params->get('text_color'),
        'bg_color' => $this->params->get('bg_color'),
        'link_color' => $this->params->get('link_color'),
        'link_hover_color' => $this->params->get('link_hover_color'),
        'header_bg_color' => $this->params->get('header_bg_color'),
        'logo_text_color' => $this->params->get('logo_text_color'),
        'menu_text_color' => $this->params->get('menu_text_color'),
        'menu_text_hover_color' => $this->params->get('menu_text_hover_color'),
        'menu_text_active_color' => $this->params->get('menu_text_active_color'),
        'menu_dropdown_bg_color' => $this->params->get('menu_dropdown_bg_color'),
        'menu_dropdown_text_color' => $this->params->get('menu_dropdown_text_color'),
        'menu_dropdown_text_hover_color' => $this->params->get('menu_dropdown_text_hover_color'),
        'menu_dropdown_text_active_color' => $this->params->get('menu_dropdown_text_active_color'),
        'footer_bg_color' => $this->params->get('footer_bg_color'),
        'footer_text_color' => $this->params->get('footer_text_color'),
        'footer_link_color' => $this->params->get('footer_link_color'),
        'footer_link_hover_color' => $this->params->get('footer_link_hover_color'),
        'topbar_bg_color' => $this->params->get('topbar_bg_color'),
        'topbar_text_color' => $this->params->get('topbar_text_color')
    );
}
else
{
    $scssVars = (array) json_decode($this->params->get('preset'));
}

$scssVars['header_height'] = $this->params->get('header_height', '60px');
$scssVars['offcanvas_width'] = $this->params->get('offcanvas_width', '300') . 'px';


//Body Background Image
if ($bg_image = $this->params->get('body_bg_image'))
{
    $body_style = 'background-image: url(' . Uri::base(true) . '/' . $bg_image . ');';
    $body_style .= 'background-repeat: ' . $this->params->get('body_bg_repeat') . ';';
    $body_style .= 'background-size: ' . $this->params->get('body_bg_size') . ';';
    $body_style .= 'background-attachment: ' . $this->params->get('body_bg_attachment') . ';';
    $body_style .= 'background-position: ' . $this->params->get('body_bg_position') . ';';
    $body_style = 'body.site {' . $body_style . '}';
    $this->addStyledeclaration($body_style);
}

//Custom CSS
if ($custom_css = $this->params->get('custom_css'))
{
    $this->addStyledeclaration($custom_css);
}


// Reading progress bar position
$progress_bar_position = $this->params->get('reading_timeline_position');

if( $app->input->get('view') == 'article' && $this->params->get('reading_time_progress', 0) ) {    
    $progress_style = 'position:fixed;';
    $progress_style .= 'z-index:9999;';
    $progress_style .= 'height:'.$this->params->get('reading_timeline_height').';';
    $progress_style .= 'background-color:'.$this->params->get('reading_timeline_bg').';';
    $progress_style .= $progress_bar_position == 'top' ? 'top:0;' : 'bottom:0;';
    $progress_style = '.sp-reading-progress-bar { '.$progress_style.' }';
    $this->addStyledeclaration($progress_style);
}


//Custom JS
if ($custom_js = $this->params->get('custom_js'))
{
    $this->addScriptdeclaration($custom_js);
}

?>

<!doctype html>
<html lang="<?php echo $this->language; ?>" dir="<?php echo $this->direction; ?>">
  <link rel="stylesheet" href="<?php echo $this->baseurl ?>/templates/shaper_languageschool/css/mobile_cards.css?v=20260506c">
  <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <?php


        // ── SEO Fixes AFK 2026-04-18 ──────────────────────────────────────
        $document = Factory::getDocument();

        // ══ SEO — Canonical, Hreflang, OG, Twitter Card, Schema.org, H1 ════════

        // 1. Canonical
        $canonicalUrl = htmlspecialchars(Uri::getInstance()->toString(), ENT_QUOTES, 'UTF-8');
        $document->addCustomTag('<link rel="canonical" href="' . $canonicalUrl . '" />');

        // 2. Hreflang
        $lang = $app->getLanguage()->getTag();
        $siteRoot = rtrim(Uri::root(), '/');
        $_reqUri = $_SERVER['REQUEST_URI'] ?? '';
        $_isZhUrl = (strpos($_reqUri, '/zh/') === 0 || $_reqUri === '/zh');
        $_isEnUrl = (strpos($_reqUri, '/en/') === 0 || $_reqUri === '/en');
        // FR : Helix Ultimate ne génère pas hreflang pour la langue par défaut → on le fait
        // ZH : Helix Ultimate génère déjà les hreflang → on ne surcharge pas
        // EN : Helix Ultimate ne génère pas hreflang pour EN → on le fait
        if (($lang === 'fr-FR' || $lang === 'fr') && !$_isZhUrl && !$_isEnUrl) {
            $document->addCustomTag('<link rel="alternate" hreflang="fr" href="' . $siteRoot . '/" />');
            $document->addCustomTag('<link rel="alternate" hreflang="zh-Hans" href="' . $siteRoot . '/zh/" />');
            $document->addCustomTag('<link rel="alternate" hreflang="en" href="' . $siteRoot . '/en/" />');
            $document->addCustomTag('<link rel="alternate" hreflang="x-default" href="' . $siteRoot . '/" />');
        } elseif ($lang === 'en-GB' || strpos($lang, 'en') === 0) {
            $document->addCustomTag('<link rel="alternate" hreflang="fr" href="' . $siteRoot . '/" />');
            $document->addCustomTag('<link rel="alternate" hreflang="zh-Hans" href="' . $siteRoot . '/zh/" />');
            $document->addCustomTag('<link rel="alternate" hreflang="en" href="' . $siteRoot . '/en/" />');
            $document->addCustomTag('<link rel="alternate" hreflang="x-default" href="' . $siteRoot . '/" />');
        }

        // 3. OG + Twitter Card
        $activeMenu  = $app->getMenu()->getActive();
        $defaultMenu = $app->getMenu()->getDefault();
        // Détection homepage robuste : active = default, ou home=1, ou URL racine
        $isHome = false;
        if ($activeMenu && $defaultMenu) {
            $isHome = ($activeMenu->id === $defaultMenu->id)
                   || ((int)$activeMenu->home === 1)
                   || ((int)$defaultMenu->home === 1 && $activeMenu->id === $defaultMenu->id);
        }
        // Toujours définir $currentPath (utilisé aussi pour Course schema)
        $currentPath = trim(\Joomla\CMS\Uri\Uri::getInstance()->getPath(), '/');
        if (!$isHome) {
            // Fallback : vérifier l'URL
            $isHome = ($currentPath === '' || $currentPath === 'index.php');
        }

        // Utiliser l'image OG définie dans SP Builder page 173 (drone photo 3840x2160)
        $ogImage = $siteRoot . '/images/2025/07/07/dji_20250621193255_0052_d.jpeg';

        // Forcer le titre de la page (court) — écrase le titre long hérité
        // On force toujours : si trop long, on le raccourcit
        $rawTitle = $document->getTitle();
        // Titres et descriptions adaptés par langue
        if (strpos($lang, 'en') === 0) {
            if ($isHome) {
                $document->setTitle('Alliance Française de Kunming — French Language School in Kunming');
            } else {
                // FaLang ne traduit pas toujours les params menu pour EN sur Joomla 5 + SP Builder.
                // Lecture directe de la traduction FaLang pour forcer le titre EN.
                try {
                    $_activeMenu = $app->getMenu()->getActive();
                    if ($_activeMenu && $_activeMenu->id) {
                        $_db = \Joomla\CMS\Factory::getDbo();
                        $_q = $_db->getQuery(true)
                            ->select('fc.value')
                            ->from($_db->quoteName('#__falang_content', 'fc'))
                            ->where('fc.reference_id = ' . (int)$_activeMenu->id)
                            ->where('fc.language_id = 1')
                            ->where('fc.reference_field = ' . $_db->quote('page_title'))
                            ->where('fc.published = 1');
                        $_db->setQuery($_q);
                        $_enTitle = $_db->loadResult();
                        if (!empty($_enTitle) && $_enTitle !== $rawTitle) {
                            $document->setTitle($_enTitle . ' - kunming-afchine.org');
                        }
                    }
                } catch (\Exception $e) {}
            }
            $ogDesc = $document->getMetaData('description') ?: 'The Alliance Française de Kunming offers French language courses for all levels, official certifications (TCF, TEF, DELF, DALF) and cultural events in Kunming, China.';
            if ($isHome) {
                $document->setMetaData('description', $ogDesc);
            }
        } elseif (strpos($lang, 'zh') === 0) {
            if ($isHome || strlen($rawTitle) > 70) {
                $document->setTitle('昆明法语联盟 — 法语课程与认证');
            }
            $ogDesc = $document->getMetaData('description') ?: '昆明法语联盟提供各级别法语课程、官方认证考试（TCF、TEF、DELF、DALF）及文化活动。';
        } else {
            if ($isHome || strlen($rawTitle) > 70) {
                $document->setTitle('Alliance Française de Kunming');
            }
            $ogDesc = $document->getMetaData('description') ?: 'Alliance Française de Kunming — cours de français, certifications TCF TEF DELF DALF, événements culturels.';
        }
        $ogTitle = $document->getTitle();

        $document->addCustomTag('<meta property="og:site_name" content="Alliance Française de Kunming" />');
        $document->addCustomTag('<meta property="og:url" content="' . htmlspecialchars($canonicalUrl, ENT_QUOTES) . '" />');
        $document->addCustomTag('<meta property="og:title" content="' . htmlspecialchars($ogTitle, ENT_QUOTES) . '" />');
        $document->addCustomTag('<meta property="og:description" content="' . htmlspecialchars($ogDesc, ENT_QUOTES) . '" />');
        $document->addCustomTag('<meta property="og:image" content="' . $ogImage . '" />');
        $document->addCustomTag('<meta property="og:image:width" content="1200" />');
        $document->addCustomTag('<meta property="og:image:height" content="800" />');
        $document->addCustomTag('<meta property="og:type" content="' . ($isHome ? 'website' : 'article') . '" />');
        $langTag = $app->getLanguage()->getTag(); // ex: fr-FR ou zh-CN
        if (strpos($langTag, 'zh') === 0) {
            $ogLocale = 'zh_CN';
        } elseif (strpos($langTag, 'en') === 0) {
            $ogLocale = 'en_GB';
        } else {
            $ogLocale = 'fr_FR';
        }
        $document->addCustomTag('<meta property="og:locale" content="' . $ogLocale . '" />');
        $ogLocaleAlternates = array_filter(['fr_FR', 'zh_CN', 'en_GB'], fn($l) => $l !== $ogLocale);
        foreach ($ogLocaleAlternates as $alt) {
            $document->addCustomTag('<meta property="og:locale:alternate" content="' . $alt . '" />');
        }

        $document->addCustomTag('<meta name="twitter:card" content="summary_large_image" />');
        $document->addCustomTag('<meta name="twitter:title" content="' . htmlspecialchars($ogTitle, ENT_QUOTES) . '" />');
        $document->addCustomTag('<meta name="twitter:description" content="' . htmlspecialchars($ogDesc, ENT_QUOTES) . '" />');
        $document->addCustomTag('<meta name="twitter:image" content="' . $ogImage . '" />');

        // 4. Schema.org Organisation (remplace FAQPage incorrect)
        if ($isHome) {
            $schema = [
                '@context' => 'https://schema.org',
                '@type'    => 'EducationalOrganization',
                'name'     => 'Alliance Française de Kunming',
                'alternateName' => 'AF Kunming',
                'url'      => $siteRoot . '/',
                'logo'     => $siteRoot . '/images/logo_afk_resize.webp',
                'image'    => $ogImage,
                'description' => 'Centre culturel et linguistique français à Kunming proposant cours de français, certifications officielles (TCF, TEF, DELF, DALF) et événements culturels.',
                'address'  => [
                    '@type'           => 'PostalAddress',
                    'streetAddress'   => 'Cuihu North Road No. 2, Yunnan University Donglu Campus',
                    'addressLocality' => 'Kunming',
                    'addressRegion'   => 'Yunnan',
                    'addressCountry'  => 'CN',
                ],
                'contactPoint' => [
                    '@type'       => 'ContactPoint',
                    'email'       => 'accueil.kunming@afchine.org',
                    'contactType' => 'customer service',
                    'availableLanguage' => ['French', 'Chinese'],
                ],
                'sameAs' => [
                    'https://www.alliancefrancaise.cn/kunming',
                ],
            ];
            $document->addCustomTag('<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</script>');
        }
        // 5. Schema.org Course — pages de cours
        // $currentPath déjà défini ligne ~178
        $courseSchemas = [
            'cours-de-francais/notre-offre-de-cours/cours-pour-adultes' => [
                '@context'    => 'https://schema.org',
                '@type'       => 'Course',
                'name'        => 'Cours de français pour adultes',
                'description' => 'Cours de français général à l\'Alliance Française de Kunming, adaptés à tous les niveaux (A1 à C2). Enseignement en présentiel par des professeurs diplômés, méthode communicative.',
                'url'         => $siteRoot . '/cours-de-francais/notre-offre-de-cours/cours-pour-adultes',
                'inLanguage'  => 'fr',
                'courseMode'  => 'onsite',
                'educationalLevel' => 'A1 à C2',
                'provider'    => [
                    '@type' => 'EducationalOrganization',
                    'name'  => 'Alliance Française de Kunming',
                    'url'   => $siteRoot . '/'
                ],
            ],
            'cours-de-francais/notre-offre-de-cours/cours-pour-enfants' => [
                '@context'    => 'https://schema.org',
                '@type'       => 'Course',
                'name'        => 'Cours de français pour enfants',
                'description' => 'Cours de français ludiques pour enfants à l\'Alliance Française de Kunming. Adaptés à tous les âges et niveaux, en présentiel, par des enseignants spécialisés.',
                'url'         => $siteRoot . '/cours-de-francais/notre-offre-de-cours/cours-pour-enfants',
                'inLanguage'  => 'fr',
                'courseMode'  => 'onsite',
                'provider'    => [
                    '@type' => 'EducationalOrganization',
                    'name'  => 'Alliance Française de Kunming',
                    'url'   => $siteRoot . '/'
                ],
            ],
            'cours-de-francais/notre-offre-de-cours/preparations-tests' => [
                '@context'    => 'https://schema.org',
                '@type'       => 'Course',
                'name'        => 'Préparation aux tests officiels de français',
                'description' => 'Cours intensifs de préparation aux certifications TCF, TEF, DELF et DALF à l\'Alliance Française de Kunming. Centre officiel agréé.',
                'url'         => $siteRoot . '/cours-de-francais/notre-offre-de-cours/preparations-tests',
                'inLanguage'  => 'fr',
                'courseMode'  => 'onsite',
                'provider'    => [
                    '@type' => 'EducationalOrganization',
                    'name'  => 'Alliance Française de Kunming',
                    'url'   => $siteRoot . '/'
                ],
            ],
            'cours-de-francais/notre-offre-de-cours/preparation-tcf-canada' => [
                '@context'    => 'https://schema.org',
                '@type'       => 'Course',
                'name'        => 'Préparation au TCF Canada',
                'description' => 'Préparation au TCF Canada à l\'Alliance Française de Kunming, centre officiel agréé. Cours ciblés pour l\'immigration au Canada, en ligne et en présentiel.',
                'url'         => $siteRoot . '/cours-de-francais/notre-offre-de-cours/preparation-tcf-canada',
                'inLanguage'  => 'fr',
                'courseMode'  => ['onsite', 'online'],
                'provider'    => [
                    '@type' => 'EducationalOrganization',
                    'name'  => 'Alliance Française de Kunming',
                    'url'   => $siteRoot . '/'
                ],
            ],
            'cours-de-francais/notre-offre-de-cours/francais-professionnel-sante' => [
                '@context'    => 'https://schema.org',
                '@type'       => 'Course',
                'name'        => 'Français médical et paramédical',
                'description' => 'Formation au français médical et paramédical à l\'Alliance Française de Kunming, conçue pour les professionnels de santé et les étudiants en médecine.',
                'url'         => $siteRoot . '/cours-de-francais/notre-offre-de-cours/francais-professionnel-sante',
                'inLanguage'  => 'fr',
                'courseMode'  => 'onsite',
                'provider'    => [
                    '@type' => 'EducationalOrganization',
                    'name'  => 'Alliance Française de Kunming',
                    'url'   => $siteRoot . '/'
                ],
            ],
        ];
        if (isset($courseSchemas[$currentPath])) {
            $document->addCustomTag('<script type="application/ld+json">' . json_encode($courseSchemas[$currentPath], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</script>');
        }

        // 6. Schema.org Course — pages certifications (passer un test)
        $certSchemas = [
            'certifications-et-diplomes/tous-nos-tests-de-langues/passer-le-tcf-canada' => [
                '@context'    => 'https://schema.org',
                '@type'       => 'Course',
                'name'        => 'TCF Canada — Test de connaissance du français',
                'description' => 'Passez le TCF Canada à l\'Alliance Française de Kunming, centre officiel agréé par France Éducation International. Test reconnu par IRCC pour l\'immigration et la citoyenneté canadienne.',
                'url'         => $siteRoot . '/certifications-et-diplomes/tous-nos-tests-de-langues/passer-le-tcf-canada',
                'inLanguage'  => 'fr',
                'courseMode'  => 'onsite',
                'educationalCredentialAwarded' => 'TCF Canada',
                'provider'    => [
                    '@type' => 'EducationalOrganization',
                    'name'  => 'Alliance Française de Kunming',
                    'url'   => $siteRoot . '/'
                ],
            ],
            'certifications-et-diplomes/tous-nos-tests-de-langues/passer-le-tcf-quebec' => [
                '@context'    => 'https://schema.org',
                '@type'       => 'Course',
                'name'        => 'TCF Québec — Test de connaissance du français',
                'description' => 'Passez le TCF Québec à l\'Alliance Française de Kunming. Test officiel reconnu par le MIFI pour l\'immigration au Québec.',
                'url'         => $siteRoot . '/certifications-et-diplomes/tous-nos-tests-de-langues/passer-le-tcf-quebec',
                'inLanguage'  => 'fr',
                'courseMode'  => 'onsite',
                'educationalCredentialAwarded' => 'TCF Québec',
                'provider'    => [
                    '@type' => 'EducationalOrganization',
                    'name'  => 'Alliance Française de Kunming',
                    'url'   => $siteRoot . '/'
                ],
            ],
            'certifications-et-diplomes/tous-nos-tests-de-langues/passer-le-tef-canada' => [
                '@context'    => 'https://schema.org',
                '@type'       => 'Course',
                'name'        => 'TEF Canada — Test d\'évaluation du français',
                'description' => 'Passez le TEF Canada à l\'Alliance Française de Kunming. Test officiel reconnu par IRCC et le MIFI pour l\'immigration et la citoyenneté canadienne.',
                'url'         => $siteRoot . '/certifications-et-diplomes/tous-nos-tests-de-langues/passer-le-tef-canada',
                'inLanguage'  => 'fr',
                'courseMode'  => 'onsite',
                'educationalCredentialAwarded' => 'TEF Canada',
                'provider'    => [
                    '@type' => 'EducationalOrganization',
                    'name'  => 'Alliance Française de Kunming',
                    'url'   => $siteRoot . '/'
                ],
            ],
            'certifications-et-diplomes/tous-nos-tests-de-langues/passer-le-tefaq' => [
                '@context'    => 'https://schema.org',
                '@type'       => 'Course',
                'name'        => 'TEFAQ — Test d\'évaluation du français adapté au Québec',
                'description' => 'Passez le TEFAQ à l\'Alliance Française de Kunming. Test reconnu par le MIFI pour l\'immigration au Québec.',
                'url'         => $siteRoot . '/certifications-et-diplomes/tous-nos-tests-de-langues/passer-le-tefaq',
                'inLanguage'  => 'fr',
                'courseMode'  => 'onsite',
                'educationalCredentialAwarded' => 'TEFAQ',
                'provider'    => [
                    '@type' => 'EducationalOrganization',
                    'name'  => 'Alliance Française de Kunming',
                    'url'   => $siteRoot . '/'
                ],
            ],
        ];
        if (isset($certSchemas[$currentPath])) {
            $document->addCustomTag('<script type="application/ld+json">' . json_encode($certSchemas[$currentPath], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</script>');
        }

        // 7. Schema.org Event — pages événements
        $eventSchemas = [
            'evenements/le-mois-de-la-francophonie' => [
                '@context'   => 'https://schema.org',
                '@type'      => 'Event',
                'name'       => 'Mois de la Francophonie 2026',
                'description' => 'Programmation culturelle du Mois de la Francophonie 2026 à l\'Alliance Française de Kunming : expositions, concerts, ateliers et conférences.',
                'url'        => $siteRoot . '/evenements/le-mois-de-la-francophonie',
                'startDate'  => '2026-03-01',
                'endDate'    => '2026-03-31',
                'eventStatus' => 'https://schema.org/EventScheduled',
                'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
                'location'   => [
                    '@type'   => 'Place',
                    'name'    => 'Alliance Française de Kunming',
                    'address' => [
                        '@type'           => 'PostalAddress',
                        'streetAddress'   => 'Cuihu North Road No. 2, Yunnan University Donglu Campus',
                        'addressLocality' => 'Kunming',
                        'addressRegion'   => 'Yunnan',
                        'addressCountry'  => 'CN',
                    ],
                ],
                'organizer'  => [
                    '@type' => 'Organization',
                    'name'  => 'Alliance Française de Kunming',
                    'url'   => $siteRoot . '/'
                ],
            ],
            'evenements/actualites' => [
                '@context'   => 'https://schema.org',
                '@type'      => 'EventSeries',
                'name'       => 'Événements culturels — Alliance Française de Kunming',
                'description' => 'Programmation culturelle de l\'Alliance Française de Kunming : ateliers, conférences, expositions et événements francophones à Kunming.',
                'url'        => $siteRoot . '/evenements/actualites',
                'organizer'  => [
                    '@type' => 'Organization',
                    'name'  => 'Alliance Française de Kunming',
                    'url'   => $siteRoot . '/'
                ],
            ],
        ];
        if (isset($eventSchemas[$currentPath])) {
            $document->addCustomTag('<script type="application/ld+json">' . json_encode($eventSchemas[$currentPath], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</script>');
        }

        // ══ Fin SEO ═══════════════════════════════════════════════════════════

        $theme->head();
        // CSS files
        // font-awesome.min.css supprimé : doublon avec SP Page Builder (font-awesome-6.min.css)
        // fa-v4-shims.css supprimé : doublon avec SP Page Builder (font-awesome-v4-shims.css)
        $theme->add_css('custom');

        // Scss files
        $theme->add_scss('master', $scssVars, 'template');
        if($this->direction == 'rtl')
        {
            $theme->add_scss('rtl', $scssVars, 'rtl');
        }
        $theme->add_scss('presets', $scssVars, 'presets/' . $scssVars['preset']);

        // JS files
        $theme->add_js('jquery.sticky.js, main.js');

        //Before Head
        if ($before_head = $this->params->get('before_head'))
        {
            echo $before_head . "\n";
        }
        ?>
        <!-- Preconnect Google Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <meta name="baidu-site-verification" content="codeva-66wEH8Semh" />         
          <!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-5N8DRLW6');</script>
<!-- End Google Tag Manager -->
    <style>
    </style>
    
<script src="<?php echo $this->baseurl ?>/templates/shaper_languageschool/js/mobile-menu.js?v=20260505a"></script>
<script src="<?php echo $this->baseurl ?>/templates/shaper_languageschool/js/calendar-i18n.js?v=20260505q"></script>
  <link rel="stylesheet" href="<?php echo $this->baseurl ?>/templates/shaper_languageschool/css/afk-styles.css?v=20260506o">
</head>
    <body class="<?php echo $theme->bodyClass(); ?>">
     <!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-5N8DRLW6"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
<?php if ($isHome): ?>
<p class="sr-only" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0;">Alliance Française de Kunming — Cours de français, certifications et culture francophone à Kunming, Chine</p>
<?php endif; ?>
    <?php if($this->params->get('preloader')) : ?>
        <div class="sp-preloader"><div></div></div>
    <?php endif; ?>

    <div class="body-wrapper">
        <div class="body-innerwrapper">
            <?php echo $theme->getHeaderStyle(); ?>
            <main id="sp-main-body" role="main">
            <?php $theme->render_layout(); ?>
            </main>
        </div>
    </div>

    <!-- Off Canvas Menu -->
    <div class="offcanvas-overlay"></div>
    <div class="offcanvas-menu">
        <a href="#" class="close-offcanvas"><span class="fa fa-remove"></span></a>
        <div class="offcanvas-inner">
            <?php if ($this->countModules('offcanvas')) : ?>
                <jdoc:include type="modules" name="offcanvas" style="sp_xhtml" />
            <?php else: ?>
                <p class="alert alert-warning">
                    <?php echo Text::_('HELIX_ULTIMATE_NO_MODULE_OFFCANVAS'); ?>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <?php $theme->after_body(); ?>

    <jdoc:include type="modules" name="debug" style="none" />
    
    <!-- Go to top -->
    <?php if ($this->params->get('goto_top', 0)) : ?>
        <a href="#" class="sp-scroll-up" aria-label="Scroll Up"><span class="fa fa-chevron-up" aria-hidden="true"></span></a>
    <?php endif; ?>

    </body>
</html>