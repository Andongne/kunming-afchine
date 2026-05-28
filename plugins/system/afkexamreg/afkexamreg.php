<?php
/**
 * @package     plg_system_afkexamreg
 * @description Après soumission du formulaire d'examen (Form 4 RSForm Pro),
 *              crée automatiquement une inscription dans rseventspro_users
 *              afin que l'événement apparaisse dans l'espace inscrit CB.
 * @version     1.0.0
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;

class PlgSystemAfkexamreg extends CMSPlugin
{
    /** ID du formulaire RSForm Pro d'inscription aux examens */
    const FORM_ID = 4;

    /**
     * Déclenché après sauvegarde d'une soumission RSForm Pro.
     * Crée une inscription RSEvents Pro si le formulaire est Form 4.
     */
    public function onRsformFrontendAfterStoreSubmissions(array $args): void
    {
        $formId       = (int) ($args['formId'] ?? 0);
        $submissionId = (int) ($args['SubmissionId'] ?? 0);

        if ($formId !== self::FORM_ID || !$submissionId) {
            return;
        }

        $db = Factory::getDbo();

        // ── 1. Récupérer les champs Session, Choix_exam et Email ──────────────
        $query = $db->getQuery(true)
            ->select([$db->qn('FieldName'), $db->qn('FieldValue')])
            ->from($db->qn('#__rsform_submission_values'))
            ->where($db->qn('SubmissionId') . ' = ' . $submissionId)
            ->where($db->qn('FieldName') . ' IN (' .
                $db->q('Session') . ', ' .
                $db->q('Choix_exam') . ', ' .
                $db->q('Email') .
            ')');
        $db->setQuery($query);
        $rows = $db->loadObjectList('FieldName');

        $sessionRaw = trim((string) ($rows['Session']->FieldValue ?? ''));
        $examType   = trim((string) ($rows['Choix_exam']->FieldValue ?? ''));
        $email      = trim((string) ($rows['Email']->FieldValue ?? ''));

        if (!$sessionRaw || !$examType || !$email) {
            return;
        }

        // ── 2. Convertir la date dd/mm/yyyy → yyyy-mm-dd (heure locale Kunming) ─
        //   RSEvents Pro stocke l'heure de début en UTC.
        //   L'examen à Kunming (UTC+8) → start UTC = date_locale - 8h
        //   Ex : 07/07/2026 à 00:00 locale = 06/07/2026 16:00 UTC
        $parts = explode('/', $sessionRaw);
        if (count($parts) !== 3) {
            return;
        }
        $localDate = sprintf('%04d-%02d-%02d', (int)$parts[2], (int)$parts[1], (int)$parts[0]);

        // ── 3. Trouver l'event RSEvents Pro (nom contient type d'examen + date locale) ─
        //   On cherche par nom (LIKE) ET date locale (CONVERT_TZ UTC→+08:00)
        $examKeyword = str_replace(['TCF Québec', 'TCF Quebec'], 'TCF Qu', $examType);
        $query = $db->getQuery(true)
            ->select($db->qn('id'))
            ->from($db->qn('#__rseventspro_events'))
            ->where($db->qn('name') . ' LIKE ' . $db->q('%' . $examKeyword . '%'))
            ->where($db->qn('published') . ' = 1')
            ->where(
                'DATE(CONVERT_TZ(' . $db->qn('start') . ", '+00:00', '+08:00')) = " .
                $db->q($localDate)
            );
        $db->setQuery($query);
        $eventId = (int) $db->loadResult();

        if (!$eventId) {
            return;
        }

        // ── 4. Récupérer l'ID utilisateur Joomla ─────────────────────────────
        //   Priorité : utilisateur connecté ; sinon chercher par email.
        $userId = (int) Factory::getUser()->id;
        if (!$userId) {
            $q = $db->getQuery(true)
                ->select($db->qn('id'))
                ->from($db->qn('#__users'))
                ->where($db->qn('email') . ' = ' . $db->q($email));
            $db->setQuery($q);
            $userId = (int) $db->loadResult();
        }

        // ── 5. Vérifier doublon ───────────────────────────────────────────────
        $check = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->qn('#__rseventspro_users'))
            ->where($db->qn('ide') . ' = ' . $eventId)
            ->where(
                '(' .
                    $db->qn('email') . ' = ' . $db->q($email) .
                    ($userId ? ' OR ' . $db->qn('idu') . ' = ' . $userId : '') .
                ')'
            );
        $db->setQuery($check);
        if ((int) $db->loadResult() > 0) {
            return;
        }

        // ── 6. Insérer l'inscription RSEvents Pro ─────────────────────────────
        $query = $db->getQuery(true)
            ->insert($db->qn('#__rseventspro_users'))
            ->columns([
                $db->qn('ide'),
                $db->qn('idu'),
                $db->qn('email'),
                $db->qn('state'),
                $db->qn('confirmed'),
                $db->qn('date'),
                $db->qn('create_user'),
                $db->qn('lang'),
            ])
            ->values(implode(', ', [
                $eventId,
                $userId,
                $db->q($email),
                1,   // state = inscrit
                0,   // confirmed = 0 (pré-inscription, en attente de paiement)
                $db->q(date('Y-m-d H:i:s')),
                1,
                $db->q(Factory::getApplication()->getLanguage()->getTag()),
            ]));
        $db->setQuery($query);
        $db->execute();
    }
}
