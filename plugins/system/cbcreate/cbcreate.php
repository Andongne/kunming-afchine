<?php
/**
 * @package     plg_system_cbcreate
 * @description Après soumission du formulaire d'inscription aux cours (Form 6)
 *              ou aux examens (Form 4) RSForm Pro, crée automatiquement un compte
 *              Joomla et un profil Community Builder.
 * @version     1.1.0
 * @author      Alliance Française de Kunming
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\User\UserHelper;

class PlgSystemCbcreate extends CMSPlugin
{
    const FORM_COURS   = 6;
    const FORM_EXAMENS = 4;
    const GROUP_REGISTERED = 2;

    // ── Point d'entrée ──────────────────────────────────────────────────────

    public function onRsformFrontendAfterStoreSubmissions(array $args): void
    {
        $formId       = (int) ($args['formId'] ?? 0);
        $submissionId = (int) ($args['SubmissionId'] ?? 0);

        if (!$submissionId) {
            return;
        }

        if ($formId === self::FORM_COURS) {
            $this->handleCours($submissionId);
        } elseif ($formId === self::FORM_EXAMENS) {
            $this->handleExamens($submissionId);
        }
    }

    // ── Form 6 — cours ──────────────────────────────────────────────────────

    private function handleCours(int $submissionId): void
    {
        $fields = $this->getFields($submissionId, [
            'Nom', 'Prenom', 'Email', 'WeChat_ID', 'Format_cours', 'Niveau',
        ]);

        $email     = trim((string) ($fields['Email']        ?? ''));
        $lastname  = trim((string) ($fields['Nom']          ?? ''));
        $firstname = trim((string) ($fields['Prenom']       ?? ''));
        $wechat    = trim((string) ($fields['WeChat_ID']    ?? ''));
        $format    = trim((string) ($fields['Format_cours'] ?? ''));
        $niveau    = trim((string) ($fields['Niveau']       ?? ''));

        if (!$email) {
            $this->log('error', '', null, 'Missing required field: email', self::FORM_COURS, $submissionId);
            return;
        }

        $cbNiveau    = $this->normalizeNiveau($niveau);
        $cbTypeCours = $this->normalizeTypeCours($format);

        $db     = Factory::getDbo();
        $userId = $this->findUserByEmail($db, $email);

        if (!$userId) {
            $userId = $this->createJoomlaUser($db, $email, $firstname, $lastname);
            if (!$userId) {
                $this->log('error', $email, null, 'DB error on users insert', self::FORM_COURS, $submissionId);
                return;
            }

            $ok = $this->createCbProfile($db, $userId, $firstname, $lastname,
                $wechat, $cbNiveau, $cbTypeCours);
            if (!$ok) {
                $this->log('error', $email, $userId, 'DB error on comprofiler insert', self::FORM_COURS, $submissionId);
                return;
            }

            $this->log('created', $email, $userId, 'OK — account and CB profile created', self::FORM_COURS, $submissionId);

        } else {
            $updated = $this->updateCbProfile($db, $userId, $firstname, $lastname,
                $wechat, $cbNiveau, $cbTypeCours);

            $msg = $updated
                ? 'email already exists, CB fields updated'
                : 'email already exists, no changes needed';
            $this->log($updated ? 'updated' : 'skipped', $email, $userId, $msg, self::FORM_COURS, $submissionId);
        }
    }

    // ── Form 4 — examens ────────────────────────────────────────────────────

    private function handleExamens(int $submissionId): void
    {
        $fields = $this->getFields($submissionId, [
            'Name', 'Prenom', 'Email', 'Tel', 'Genre', 'Birth_date', 'Pays',
        ]);

        $email     = trim((string) ($fields['Email']      ?? ''));
        $lastname  = trim((string) ($fields['Name']       ?? ''));
        $firstname = trim((string) ($fields['Prenom']     ?? ''));
        $telephone = trim((string) ($fields['Tel']        ?? ''));
        $sexe      = trim((string) ($fields['Genre']      ?? ''));
        $pays      = trim((string) ($fields['Pays']       ?? ''));
        $birthRaw  = trim((string) ($fields['Birth_date'] ?? ''));

        if (!$email) {
            $this->log('error', '', null, 'Missing required field: email', self::FORM_EXAMENS, $submissionId);
            return;
        }

        $cbSexe      = $this->normalizeSexe($sexe);
        $cbNaissance = $this->normalizeBirthdate($birthRaw);

        $db     = Factory::getDbo();
        $userId = $this->findUserByEmail($db, $email);

        if (!$userId) {
            $userId = $this->createJoomlaUser($db, $email, $firstname, $lastname);
            if (!$userId) {
                $this->log('error', $email, null, 'DB error on users insert', self::FORM_EXAMENS, $submissionId);
                return;
            }

            $ok = $this->createCbProfile($db, $userId, $firstname, $lastname,
                '', '', '', $telephone, $cbSexe, $cbNaissance, $pays);
            if (!$ok) {
                $this->log('error', $email, $userId, 'DB error on comprofiler insert', self::FORM_EXAMENS, $submissionId);
                return;
            }

            $this->log('created', $email, $userId, 'OK — account and CB profile created (exam)', self::FORM_EXAMENS, $submissionId);

        } else {
            $updated = $this->updateCbProfile($db, $userId, $firstname, $lastname,
                '', '', '', $telephone, $cbSexe, $cbNaissance, $pays);

            $msg = $updated
                ? 'email already exists, CB fields updated (exam)'
                : 'email already exists, no changes needed (exam)';
            $this->log($updated ? 'updated' : 'skipped', $email, $userId, $msg, self::FORM_EXAMENS, $submissionId);
        }
    }

    // ── Joomla user ─────────────────────────────────────────────────────────

    private function findUserByEmail(\Joomla\Database\DatabaseInterface $db, string $email): int
    {
        $q = $db->getQuery(true)
            ->select($db->qn('id'))
            ->from($db->qn('#__users'))
            ->where($db->qn('email') . ' = ' . $db->q($email));
        $db->setQuery($q);
        return (int) $db->loadResult();
    }

    private function createJoomlaUser(\Joomla\Database\DatabaseInterface $db, string $email, string $firstname, string $lastname): int
    {
        $fullname = trim($firstname . ' ' . $lastname) ?: $email;
        $username = $this->generateUsername($db, $email, $firstname, $lastname);
        $password = UserHelper::hashPassword(UserHelper::genRandomPassword(16));
        $now      = Factory::getDate()->toSql();

        try {
            $q = $db->getQuery(true)
                ->insert($db->qn('#__users'))
                ->columns([
                    $db->qn('name'),
                    $db->qn('username'),
                    $db->qn('email'),
                    $db->qn('password'),
                    $db->qn('block'),
                    $db->qn('registerDate'),
                    $db->qn('params'),
                ])
                ->values(implode(', ', [
                    $db->q($fullname),
                    $db->q($username),
                    $db->q($email),
                    $db->q($password),
                    0,
                    $db->q($now),
                    $db->q('{}'),
                ]));
            $db->setQuery($q);
            $db->execute();
            $userId = (int) $db->insertid();
        } catch (\Exception $e) {
            return 0;
        }

        if (!$userId) {
            return 0;
        }

        try {
            $q = $db->getQuery(true)
                ->insert($db->qn('#__user_usergroup_map'))
                ->columns([$db->qn('user_id'), $db->qn('group_id')])
                ->values($userId . ', ' . self::GROUP_REGISTERED);
            $db->setQuery($q);
            $db->execute();
        } catch (\Exception $e) {
            // Non bloquant
        }

        return $userId;
    }

    private function generateUsername(\Joomla\Database\DatabaseInterface $db, string $email, string $firstname, string $lastname): string
    {
        $base = strtolower($this->removeAccents(trim($firstname . $lastname)));
        $base = preg_replace('/[^a-z0-9]/', '', $base);

        if (!$base) {
            $base = strtolower(preg_replace('/[^a-z0-9]/i', '', explode('@', $email)[0]));
        }

        $base     = substr($base, 0, 30) ?: 'user';
        $username = $base;
        $i        = 1;

        while (true) {
            $q = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->qn('#__users'))
                ->where($db->qn('username') . ' = ' . $db->q($username));
            $db->setQuery($q);
            if ((int) $db->loadResult() === 0) {
                break;
            }
            $username = $base . $i;
            $i++;
        }

        return $username;
    }

    // ── Community Builder ────────────────────────────────────────────────────

    private function createCbProfile(
        \Joomla\Database\DatabaseInterface $db,
        int    $userId,
        string $firstname = '',
        string $lastname  = '',
        string $wechat    = '',
        string $niveau    = '',
        string $typeCours = '',
        string $telephone = '',
        string $sexe      = '',
        string $naissance = '',
        string $pays      = ''
    ): bool {
        try {
            $now = Factory::getDate()->toSql();
            $q   = $db->getQuery(true)
                ->insert($db->qn('#__comprofiler'))
                ->columns([
                    $db->qn('id'),
                    $db->qn('user_id'),
                    $db->qn('firstname'),
                    $db->qn('lastname'),
                    $db->qn('approved'),
                    $db->qn('confirmed'),
                    $db->qn('lastupdatedate'),
                    $db->qn('registeripaddr'),
                    $db->qn('cbactivation'),
                    $db->qn('hits'),
                    $db->qn('message_last_sent'),
                    $db->qn('message_number_sent'),
                    $db->qn('avatarapproved'),
                    $db->qn('canvasapproved'),
                    $db->qn('canvasposition'),
                    $db->qn('banned'),
                    $db->qn('acceptedterms'),
                    $db->qn('acceptedtermsconsent'),
                    $db->qn('cb_wechat'),
                    $db->qn('cb_niveau'),
                    $db->qn('cb_type_cours'),
                    $db->qn('cb_telephone'),
                    $db->qn('cb_sexe'),
                    $db->qn('cb_datenaissance'),
                    $db->qn('cb_pays'),
                ])
                ->values(implode(', ', [
                    $userId,
                    $userId,
                    $db->q($firstname),
                    $db->q($lastname),
                    1,
                    1,
                    $db->q($now),
                    $db->q(''),
                    $db->q(''),
                    0,
                    $db->q('1970-01-01 00:00:00'),
                    0,
                    1,
                    1,
                    50,
                    0,
                    0,
                    $db->q('1970-01-01 00:00:00'),
                    $db->q($wechat),
                    $db->q($niveau),
                    $db->q($typeCours),
                    $db->q($telephone),
                    $db->q($sexe),
                    $naissance ? $db->q($naissance) : 'NULL',
                    $db->q($pays),
                ]));
            $db->setQuery($q);
            $db->execute();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function updateCbProfile(
        \Joomla\Database\DatabaseInterface $db,
        int    $userId,
        string $firstname = '',
        string $lastname  = '',
        string $wechat    = '',
        string $niveau    = '',
        string $typeCours = '',
        string $telephone = '',
        string $sexe      = '',
        string $naissance = '',
        string $pays      = ''
    ): bool {
        $q = $db->getQuery(true)
            ->select([
                $db->qn('firstname'),
                $db->qn('lastname'),
                $db->qn('cb_wechat'),
                $db->qn('cb_niveau'),
                $db->qn('cb_type_cours'),
                $db->qn('cb_telephone'),
                $db->qn('cb_sexe'),
                $db->qn('cb_datenaissance'),
                $db->qn('cb_pays'),
            ])
            ->from($db->qn('#__comprofiler'))
            ->where($db->qn('id') . ' = ' . $userId);
        $db->setQuery($q);
        $current = $db->loadAssoc();

        if (!$current) {
            return $this->createCbProfile($db, $userId, $firstname, $lastname,
                $wechat, $niveau, $typeCours, $telephone, $sexe, $naissance, $pays);
        }

        $set = [];
        $now = Factory::getDate()->toSql();

        $map = [
            'firstname'     => $firstname,
            'lastname'      => $lastname,
            'cb_wechat'     => $wechat,
            'cb_niveau'     => $niveau,
            'cb_type_cours' => $typeCours,
            'cb_telephone'  => $telephone,
            'cb_sexe'       => $sexe,
            'cb_pays'       => $pays,
        ];

        foreach ($map as $col => $val) {
            if ($val !== '' && (string)($current[$col] ?? '') !== $val) {
                $set[] = $db->qn($col) . ' = ' . $db->q($val);
            }
        }

        // cb_datenaissance : traitement séparé (type DATE)
        if ($naissance !== '' && (string)($current['cb_datenaissance'] ?? '') !== $naissance) {
            $set[] = $db->qn('cb_datenaissance') . ' = ' . $db->q($naissance);
        }

        if (empty($set)) {
            return false;
        }

        $set[] = $db->qn('lastupdatedate') . ' = ' . $db->q($now);

        try {
            $q = $db->getQuery(true)
                ->update($db->qn('#__comprofiler'))
                ->set($set)
                ->where($db->qn('id') . ' = ' . $userId);
            $db->setQuery($q);
            $db->execute();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    // ── Normalisation des valeurs RSForm → CB ────────────────────────────────

    /**
     * "C1 Avance" → "C1", "B2 Intermediaire" → "B2"
     */
    private function normalizeNiveau(string $raw): string
    {
        if (preg_match('/\b([A-C][12](?:\.\d)?)\b/i', $raw, $m)) {
            return strtoupper($m[1]);
        }
        return $raw;
    }

    /**
     * "Groupes (de 6 à 12 personnes)" → "Cours groupes", etc.
     */
    private function normalizeTypeCours(string $raw): string
    {
        $f = strtolower($this->removeAccents($raw));
        if (strpos($f, 'essai') !== false)  return "Cours d'essai";
        if (strpos($f, 'vip')   !== false)  return 'Cours VIP';
        if (strpos($f, 'petit') !== false)  return 'Petits groupes';
        if (strpos($f, 'group') !== false)  return 'Cours groupes';
        return $raw;
    }

    /**
     * "Madame" → "F", "Monsieur" → "M"
     */
    private function normalizeSexe(string $raw): string
    {
        $f = strtolower($this->removeAccents($raw));
        if (strpos($f, 'madame') !== false || strpos($f, 'dame') !== false) return 'F';
        if (strpos($f, 'monsieur') !== false)                                return 'M';
        return $raw;
    }

    /**
     * "6 / 5 / 1972" ou "06/05/1972" → "1972-05-06" (yyyy-mm-dd)
     * Retourne '' si invalide.
     */
    private function normalizeBirthdate(string $raw): string
    {
        $clean = preg_replace('/\s*\/\s*/', '/', trim($raw));
        $parts = explode('/', $clean);
        if (count($parts) !== 3) return '';
        [$d, $m, $y] = $parts;
        $d = (int)$d; $m = (int)$m; $y = (int)$y;
        if ($d < 1 || $d > 31 || $m < 1 || $m > 12 || $y < 1900 || $y > 2100) return '';
        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function getFields(int $submissionId, array $fieldNames): array
    {
        $db     = Factory::getDbo();
        $quoted = array_map([$db, 'q'], $fieldNames);
        $q      = $db->getQuery(true)
            ->select([$db->qn('FieldName'), $db->qn('FieldValue')])
            ->from($db->qn('#__rsform_submission_values'))
            ->where($db->qn('SubmissionId') . ' = ' . $submissionId)
            ->where($db->qn('FieldName') . ' IN (' . implode(',', $quoted) . ')');
        $db->setQuery($q);
        $result = [];
        foreach ($db->loadObjectList() ?? [] as $row) {
            $result[$row->FieldName] = $row->FieldValue;
        }
        return $result;
    }

    private function removeAccents(string $str): string
    {
        $from = ['à','á','â','ã','ä','å','æ','ç','è','é','ê','ë',
                 'ì','í','î','ï','ñ','ò','ó','ô','õ','ö','ø','ù',
                 'ú','û','ü','ý','ÿ','À','Á','Â','Ã','Ä','Å','Æ',
                 'Ç','È','É','Ê','Ë','Ì','Í','Î','Ï','Ñ','Ò','Ó',
                 'Ô','Õ','Ö','Ø','Ù','Ú','Û','Ü','Ý'];
        $to   = ['a','a','a','a','a','a','ae','c','e','e','e','e',
                 'i','i','i','i','n','o','o','o','o','o','o','u',
                 'u','u','u','y','y','A','A','A','A','A','A','AE',
                 'C','E','E','E','E','I','I','I','I','N','O','O',
                 'O','O','O','O','U','U','U','U','Y'];
        return str_replace($from, $to, $str);
    }

    private function log(
        string $action,
        string $email,
        ?int   $userId,
        string $msg,
        int    $formId = 0,
        int    $subId  = 0
    ): void {
        $logFile = JPATH_ROOT . '/logs/cbcreate.log';
        $entry   = json_encode([
            'ts'      => (new \DateTime('now', new \DateTimeZone('Asia/Shanghai')))->format('c'),
            'form'    => $formId,
            'sub'     => $subId,
            'email'   => $email,
            'action'  => $action,
            'user_id' => $userId,
            'msg'     => $msg,
        ], JSON_UNESCAPED_UNICODE);

        @file_put_contents($logFile, $entry . "\n", FILE_APPEND | LOCK_EX);
    }
}
