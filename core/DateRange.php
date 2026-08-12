<?php

/**
 * DateRange
 * Turns ?period=daily|weekly|monthly|custom (+ date / start / end) into a
 * concrete [start, end, label] date range. Originally lived as a private
 * method inside ReportController, but Cashier and Worker reports both need
 * the exact same logic now, so it's a shared utility instead of being
 * copy-pasted three times.
 */
class DateRange
{
    /** Returns [range, errorMessageOrNull]. */
    public static function resolve(array $params): array
    {
        $period = $params['period'] ?? 'daily';
        $referenceDate = $params['date'] ?? date('Y-m-d');

        // Guard against garbage input turning into a confusing PHP date error.
        if (!self::isValidDate($referenceDate)) {
            $referenceDate = date('Y-m-d');
        }

        switch ($period) {
            case 'weekly':
                $start = date('Y-m-d', strtotime('monday this week', strtotime($referenceDate)));
                $end = date('Y-m-d', strtotime('sunday this week', strtotime($referenceDate)));
                $label = 'Weekly';
                break;

            case 'monthly':
                $start = date('Y-m-01', strtotime($referenceDate));
                $end = date('Y-m-t', strtotime($referenceDate));
                $label = 'Monthly';
                break;

            case 'custom':
                $start = $params['start'] ?? date('Y-m-d');
                $end = $params['end'] ?? date('Y-m-d');

                if (!self::isValidDate($start) || !self::isValidDate($end)) {
                    return [['start' => date('Y-m-d'), 'end' => date('Y-m-d'), 'label' => 'Custom', 'period' => 'custom', 'date' => $referenceDate], 'Please enter two valid dates.'];
                }
                if ($start > $end) {
                    return [['start' => $start, 'end' => $end, 'label' => 'Custom', 'period' => 'custom', 'date' => $referenceDate], 'Start date must be before end date.'];
                }
                $label = 'Custom';
                break;

            case 'daily':
            default:
                $start = $referenceDate;
                $end = $referenceDate;
                $label = 'Daily';
                $period = 'daily';
                break;
        }

        return [[
            'start' => $start,
            'end' => $end,
            'label' => $label,
            'period' => $period,
            'date' => $referenceDate,
        ], null];
    }

    public static function isValidDate(string $value): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $value);
        return $d && $d->format('Y-m-d') === $value;
    }
}
