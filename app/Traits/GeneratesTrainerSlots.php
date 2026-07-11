<?php

namespace App\Traits;

use Carbon\Carbon;

trait GeneratesTrainerSlots
{
    private function generateSlots(string $start, string $end, int $durationMinutes = 120): array
    {
        $slots   = [];
        $current = Carbon::createFromFormat('H:i', substr($start, 0, 5));
        $endTime = Carbon::createFromFormat('H:i', substr($end,   0, 5));

        while ($current->copy()->addMinutes($durationMinutes)->lte($endTime)) {
            $slotEnd = $current->copy()->addMinutes($durationMinutes);
            $slots[] = $current->format('H:i') . '-' . $slotEnd->format('H:i');
            $current->addMinutes($durationMinutes);
        }

        return $slots;
    }
}
