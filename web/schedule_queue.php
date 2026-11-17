
<?php
/*
 * Filename: schedule_queue.php
 *
 * Displays the approved Lightboard schedule queue.
 *
 * Responsibilities:
 * - Show currently active schedules.
 * - Show upcoming approved schedules.
 * - Show finished schedules.
 * - Display scheduled display name, brightness, and times.
 * - Automatically refresh the queue.
 *
 * Author: Sam Vossen
 * Date: 2026.11.17
 */

date_default_timezone_set('America/New_York');

require __DIR__ . '/includes/auth/require_login.php';
require_once __DIR__ . '/includes/lightboard/core/database.php';

/*
 * Escape output.
 */
function queueEscape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/*
 * Format a schedule date.
 */
function queueFormatDate($value): string
{
    if (empty($value)) {
        return 'Unknown';
    }

    try {
        $date = new DateTimeImmutable($value);
        return $date->format('M j, Y');
    } catch (Throwable $e) {
        return queueEscape($value);
    }
}

/*
 * Format a schedule time.
 */
function queueFormatTime($value): string
{
    if (empty($value)) {
        return 'Unknown';
    }

    try {
        $date = new DateTimeImmutable($value);
        return $date->format('g:i A');
    } catch (Throwable $e) {
        return queueEscape($value);
    }
}

/*
 * Retrieve approved schedules.
 */
$schedules = [];
$loadError = false;

try {
    $pdo = db();

    $sql = "
        SELECT
            s.eventID,
            s.startEvent,
            s.endEvent,
            s.brightness,
            s.note,
            s.pDisplayID,
            s.cDisplayID,
            COALESCE(c.name, p.name, 'Unknown Display') AS displayName,
            CASE
                WHEN s.cDisplayID IS NOT NULL THEN 'Custom Display'
                WHEN s.pDisplayID IS NOT NULL THEN 'Preset Display'
                ELSE 'Unknown'
            END AS displayType
        FROM schedule s
        LEFT JOIN customDisplay c
            ON c.cDisplayID = s.cDisplayID
        LEFT JOIN presetDisplay p
            ON p.pDisplayID = s.pDisplayID
        WHERE s.status = 'approved'
        ORDER BY s.startEvent ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('Schedule queue error: ' . $e->getMessage());
    $loadError = true;
}

/*
 * Separate schedules by their current status.
 */
$activeSchedules = [];
$upcomingSchedules = [];
$finishedSchedules = [];

$now = new DateTimeImmutable('now');

foreach ($schedules as $schedule) {
    try {
        $start = new DateTimeImmutable($schedule['startEvent']);
        $end = new DateTimeImmutable($schedule['endEvent']);

        if ($now >= $start && $now < $end) {
            $schedule['queueStatus'] = 'Active';
            $activeSchedules[] = $schedule;

        } elseif ($now < $start) {
            $schedule['queueStatus'] = 'Upcoming';
            $upcomingSchedules[] = $schedule;

        } else {
            $schedule['queueStatus'] = 'Finished';
            $finishedSchedules[] = $schedule;
        }

    } catch (Throwable $e) {
        error_log(
            'Invalid schedule dates for event ' .
            ($schedule['eventID'] ?? 'unknown')
        );
    }
}

/*
 * Render a schedule card.
 */
function renderScheduleCard(array $schedule): void
{
    $status = $schedule['queueStatus'];

    $statusClass = strtolower($status);

    $brightness = max(
        1,
        min(100, (int)($schedule['brightness'] ?? 35))
    );
    ?>

    <article class="queue-card <?= queueEscape($statusClass) ?>">

        <div class="queue-card-top">

            <div>
                <span class="queue-event">
                    Event #<?= queueEscape($schedule['eventID']) ?>
                </span>

                <h3>
                    <?= queueEscape($schedule['displayName']) ?>
                </h3>

                <p class="display-type">
                    <?= queueEscape($schedule['displayType']) ?>
                </p>
            </div>

            <span class="status-badge <?= queueEscape($statusClass) ?>">
                <?= queueEscape($status) ?>
            </span>

        </div>

        <div class="queue-details">

            <div class="detail">
                <span class="detail-label">Starts</span>
                <strong>
                    <?= queueFormatDate($schedule['startEvent']) ?>
                </strong>
                <span>
                    <?= queueFormatTime($schedule['startEvent']) ?>
                </span>
            </div>

            <div class="detail">
                <span class="detail-label">Ends</span>
                <strong>
                    <?= queueFormatDate($schedule['endEvent']) ?>
                </strong>
                <span>
                    <?= queueFormatTime($schedule['endEvent']) ?>
                </span>
            </div>

            <div class="detail">
                <span class="detail-label">Brightness</span>
                <strong><?= $brightness ?>%</strong>
            </div>

        </div>

        <?php if (!empty($schedule['note'])): ?>
            <div class="queue-note">
                <?= nl2br(queueEscape($schedule['note'])) ?>
            </div>
        <?php endif; ?>

    </article>

    <?php
}

/*
 * Include shared navigation.
 */
require __DIR__ . '/includes/layout/navbar.php';
?>

<style>
.queue-page {
    max-width: 1100px;
    margin: 30px auto;
    padding: 0 20px 50px;
}

.queue-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 25px;
}

.queue-header h1 {
    margin: 0;
}

.queue-refresh {
    color: #777;
    font-size: 13px;
}

.queue-section {
    margin-top: 35px;
}

.queue-section h2 {
    margin-bottom: 15px;
}

.queue-count {
    color: #888;
    font-size: 14px;
    font-weight: normal;
}

.queue-card {
    background: var(--bs-card-bg, #fff);
    color: var(--bs-body-color, #222);
    border: 1px solid var(--bs-border-color, #ddd);
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 15px;
    box-shadow: 0 2px 5px rgba(0,0,0,.04);
}

.queue-card.active {
    border-left: 5px solid #198754;
}

.queue-card.upcoming {
    border-left: 5px solid #0d6efd;
}

.queue-card.finished {
    border-left: 5px solid #888;
    opacity: .75;
}

.queue-card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
}

.queue-card h3 {
    margin: 5px 0;
    font-size: 20px;
}

.queue-event {
    color: #888;
    font-size: 12px;
}

.display-type {
    color: #888;
    font-size: 13px;
    margin: 0;
}

.status-badge {
    border-radius: 20px;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: bold;
    white-space: nowrap;
}

.status-badge.active {
    background: #d1e7dd;
    color: #0f5132;
}

.status-badge.upcoming {
    background: #cfe2ff;
    color: #084298;
}

.status-badge.finished {
    background: #e2e3e5;
    color: #41464b;
}

.queue-details {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 15px;
    margin-top: 22px;
}

.detail {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.detail-label {
    color: #888;
    font-size: 12px;
}

.detail strong {
    font-size: 15px;
}

.queue-note {
    border-top: 1px solid var(--bs-border-color, #ddd);
    margin-top: 18px;
    padding-top: 12px;
    color: var(--bs-secondary-color, #666);
    font-size: 14px;
}

.queue-empty {
    color: #888;
    padding: 20px;
    text-align: center;
    border: 1px dashed var(--bs-border-color, #ccc);
    border-radius: 10px;
}

.queue-error {
    padding: 15px;
    border-radius: 8px;
    background: #f8d7da;
    color: #842029;
}

@media (max-width: 600px) {
    .queue-details {
        grid-template-columns: 1fr 1fr;
    }

    .queue-card-top {
        flex-direction: column;
    }
}
</style>

<main class="queue-page">

    <div class="queue-header">
        <div>
            <h1>Lightboard Schedule Queue</h1>
            <p>Approved displays and their scheduled run times.</p>
        </div>

        <div class="queue-refresh">
            Auto-refreshes every 30 seconds
        </div>
    </div>

    <?php if ($loadError): ?>

        <div class="queue-error">
            Unable to load the schedule queue.
            Check the server error log for details.
        </div>

    <?php else: ?>

        <section class="queue-section">

            <h2>
                Currently Active
                <span class="queue-count">
                    (<?= count($activeSchedules) ?>)
                </span>
            </h2>

            <?php if (empty($activeSchedules)): ?>
                <div class="queue-empty">
                    No approved schedules are running right now.
                </div>
            <?php else: ?>
                <?php foreach ($activeSchedules as $schedule): ?>
                    <?php renderScheduleCard($schedule); ?>
                <?php endforeach; ?>
            <?php endif; ?>

        </section>

        <section class="queue-section">

            <h2>
                Upcoming
                <span class="queue-count">
                    (<?= count($upcomingSchedules) ?>)
                </span>
            </h2>

            <?php if (empty($upcomingSchedules)): ?>
                <div class="queue-empty">
                    No upcoming approved schedules.
                </div>
            <?php else: ?>
                <?php foreach ($upcomingSchedules as $schedule): ?>
                    <?php renderScheduleCard($schedule); ?>
                <?php endforeach; ?>
            <?php endif; ?>

        </section>

        <section class="queue-section">

            <h2>
                Finished
                <span class="queue-count">
                    (<?= count($finishedSchedules) ?>)
                </span>
            </h2>

            <?php if (empty($finishedSchedules)): ?>
                <div class="queue-empty">
                    No finished schedules.
                </div>
            <?php else: ?>
                <?php foreach ($finishedSchedules as $schedule): ?>
                    <?php renderScheduleCard($schedule); ?>
                <?php endforeach; ?>
            <?php endif; ?>

        </section>

    <?php endif; ?>

</main>

<script>
    // Refresh the queue every 30 seconds.
    setTimeout(function () {
        window.location.reload();
    }, 30000);
</script>

<?php
require __DIR__ . '/includes/layout/footer.php';
?>
