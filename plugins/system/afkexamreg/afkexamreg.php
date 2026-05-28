<?php
/**
 * @package     plg_system_afkexamreg
 * @description Après soumission du formulaire d'examen (Form 4) ou du formulaire
 *              de cours (Form 6) RSForm Pro, crée automatiquement une inscription
 *              dans rseventspro_users afin que l'événement apparaisse dans
 *              l'espace inscrit CB.
 * @version     1.1.0
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;

class PlgSystemAfkexamreg extends CMSPlugin
{
    const FORM_EXAM   = 4;
    const FORM_COURS  = 6;

    public function onRsformFrontendAfterStoreSubmissions(array $args): void
    {
        $formId       = (int) ($args['formId'] ?? 0);
        $submissionId = (int) ($args['SubmissionId'] ?? 0);

        if (!$submissionId) {
            return;
        }

        if ($formId === self::FORM_EXAM) {
            $this->handleExam($submissionId);
        } elseif ($formId === self::FORM_COURS) {
            $this->handleCours($submissionId);
        }
    }

    // ── Form 4 : examens ────────────────────────────────────────────────────

    private function handleExam(int $submissionId): void
    {
        $db = Factory::getDbo();

        $rows = $this->getFields($submissionId, ['Session', 'Choix_exam', 'Email']);

        $sessionRaw = trim((string) ($rows['Session'] ?? ''));
        $examType   = trim((string) ($rows['Choix_exam'] ?? ''));
        $email      = trim((string) ($rows['Email'] ?? ''));

        if (!$sessionRaw || !$examType || !$email) {
            return;
        }

        $localDate = $this->parseDate($sessionRaw);
        if (!$localDate) {
            return;
        }

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

        $this->insertRegistration($eventId, $email);
    }

    // ── Form 6 : cours ──────────────────────────────────────────────────────

    private function handleCours(int $submissionId): void
    {
        $db = Factory::getDbo();

        $rows = $this->getFields($submissionId, ['Session', 'Format_cours', 'Niveau', 'Email']);

        $sessionRaw = trim((string) ($rows['Session'] ?? ''));
        $format     = trim((string) ($rows['Format_cours'] ?? ''));
        $niveau     = trim((string) ($rows['Niveau'] ?? ''));
        $email      = trim((string) ($rows['Email'] ?? ''));

        if (!$sessionRaw || !$email) {
            return;
        }

        $localDate = $this->parseDate($sessionRaw);
        if (!$localDate) {
            return;
        }

        // Mapper Format_cours → ID de catégorie RSEvents Pro
        $catId = $this->formatToCatId($format);

        // Chercher l'event par date + catégorie (+ niveau en hint si plusieurs résultats)
        $query = $db->getQuery(true)
            ->select(['e.' . $db->qn('id'), 'e.' . $db->qn('name')])
            ->from($db->qn('#__rseventspro_events', 'e'))
            ->join('INNER', $db->qn('#__rseventspro_taxonomy', 't') .
                ' ON ' . $db->qn('t.ide') . ' = ' . $db->qn('e.id') .
                ' AND ' . $db->qn('t.type') . ' = ' . $db->q('category'))
            ->where($db->qn('e.published') . ' = 1')
            ->where(
                'DATE(CONVERT_TZ(' . $db->qn('e.start') . ", '+00:00', '+08:00')) = " .
                $db->q($localDate)
            );

        if ($catId) {
            $query->where($db->qn('t.id') . ' = ' . $catId);
        }

        $db->setQuery($query);
        $candidates = $db->loadObjectList();

        if (empty($candidates)) {
            return;
        }

        // Si plusieurs événements ce jour-là, tenter de matcher par niveau
        $eventId = 0;
        if (count($candidates) === 1) {
            $eventId = (int) $candidates[0]->id;
        } elseif ($niveau) {
            // Extraire le code de niveau (ex: "C1 Avance" → "C1", "B2" → "B2")
            $niveauCode = preg_match('/\b([A-C][12](?:\.\d)?)\b/i', $niveau, $m) ? strtoupper($m[1]) : '';
            foreach ($candidates as $c) {
                if ($niveauCode && stripos($c->name, $niveauCode) !== false) {
                    $eventId = (int) $c->id;
                    break;
                }
            }
            // Fallback : premier candidat
            if (!$eventId) {
                $eventId = (int) $candidates[0]->id;
            }
        } else {
            $eventId = (int) $candidates[0]->id;
        }

        if (!$eventId) {
            return;
        }

        $this->insertRegistration($eventId, $email);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Retourne un tableau FieldName → FieldValue pour les champs demandés.
     */
    private function getFields(int $submissionId, array $fieldNames): array
    {
        $db = Factory::getDbo();
        $quoted = array_map([$db, 'q'], $fieldNames);
        $query = $db->getQuery(true)
            ->select([$db->qn('FieldName'), $db->qn('FieldValue')])
            ->from($db->qn('#__rsform_submission_values'))
            ->where($db->qn('SubmissionId') . ' = ' . $submissionId)
            ->where($db->qn('FieldName') . ' IN (' . implode(',', $quoted) . ')');
        $db->setQuery($query);
        $result = [];
        foreach ($db->loadObjectList() ?? [] as $row) {
            $result[$row->FieldName] = $row->FieldValue;
        }
        return $result;
    }

    /**
     * Convertit dd/mm/yyyy → yyyy-mm-dd. Retourne '' si invalide.
     */
    private function parseDate(string $raw): string
    {
        $parts = explode('/', $raw);
        if (count($parts) !== 3) {
            return '';
        }
        return sprintf('%04d-%02d-%02d', (int)$parts[2], (int)$parts[1], (int)$parts[0]);
    }

    /**
     * Mappe la valeur du champ Format_cours à l'ID de catégorie RSEvents Pro.
     * Retourne 0 si non reconnu (pas de filtre catégorie).
     */
    private function formatToCatId(string $format): int
    {
        $f = strtolower($format);
        if (strpos($f, 'essai') !== false)                        return 51;
        if (strpos($f, 'vip') !== false)                          return 52;
        if (strpos($f, 'petit') !== false)                        return 53;
        if (strpos($f, 'groupe') !== false)                       return 50;
        return 0;
    }

    /**
     * Vérifie doublon et insère l'inscription RSEvents Pro.
     */
    private function insertRegistration(int $eventId, string $email): void
    {
        $db = Factory::getDbo();

        $userId = (int) Factory::getUser()->id;
        if (!$userId) {
            $q = $db->getQuery(true)
                ->select($db->qn('id'))
                ->from($db->qn('#__users'))
                ->where($db->qn('email') . ' = ' . $db->q($email));
            $db->setQuery($q);
            $userId = (int) $db->loadResult();
        }

        // Vérifier doublon
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
                1,
                0,
                $db->q(date('Y-m-d H:i:s')),
                1,
                $db->q(Factory::getApplication()->getLanguage()->getTag()),
            ]));
        $db->setQuery($query);
        $db->execute();
    }
}
