<?php defined('_JEXEC') or die; ?>

<div class="afk-upcoming-exams">
<?php if (empty($events)) : ?>
    <p class="afk-no-events"><?php echo $isZh ? '暂无即将到来的考试。' : 'Aucune session prévue.'; ?></p>
<?php else : ?>
    <ul class="afk-exam-list">
    <?php foreach ($events as $ev) :
        // Titre : tronquer avant " — "
        $rawName = isset($translations[$ev->id]['name']) ? $translations[$ev->id]['name'] : $ev->name;
        $parts   = explode(' — ', $rawName, 2);
        $title   = $parts[0];
        $dateStr = isset($parts[1]) ? $parts[1] : '';

        // Date de l'examen
        $startTs = strtotime($ev->start);
        $dateFormatted = afk_fmt_date($startTs, $isZh, $enMonths, $frMonths, $zhMonths);

        // Date limite d'inscription
        $deadlineFormatted = '';
        if ($showDL && !empty($ev->end_registration) && $ev->end_registration !== '0000-00-00 00:00:00') {
            $dlTs = strtotime($ev->end_registration);
            $deadlineFormatted = afk_fmt_date($dlTs, $isZh, $enMonths, $frMonths, $zhMonths);
        }

        // URL
        $url = afk_event_url($ev->id, $ev->name);
    ?>
        <li class="afk-exam-item">
            <a href="<?php echo $url; ?>" class="afk-exam-link">
                <span class="afk-exam-title"><?php echo htmlspecialchars($title); ?></span>
                <span class="afk-exam-date"><?php echo $dateFormatted; ?></span>
                <?php if ($deadlineFormatted) : ?>
                <span class="afk-exam-deadline">
                    <?php echo $isZh ? '报名截止：' : 'Inscription avant le '; ?>
                    <?php echo $deadlineFormatted; ?>
                </span>
                <?php endif; ?>
            </a>
        </li>
    <?php endforeach; ?>
    </ul>
<?php endif; ?>
</div>
