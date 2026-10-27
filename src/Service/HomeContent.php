<?php

namespace App\Service;

class HomeContent
{
    public function __construct(
        private ApiClient $apiClient,
        private JazdyClient $jazdy,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function forPage(): array
    {
        $postsResponse = $this->apiClient->getRecentPosts(3);
        $posts = $postsResponse['data'] ?? [];
        if (!\is_array($posts)) {
            $posts = [];
        }

        $availability = $this->jazdy->slots();
        $days = $this->groupDays($availability['slots'] ?? []);

        return [
            'posts' => $posts,
            'availability' => $availability,
            'days' => $days,
            'horses' => $this->horsesIn($days),
        ];
    }

    public function formatWhen(string $iso): string
    {
        try {
            $dt = (new \DateTimeImmutable($iso))->setTimezone(new \DateTimeZone('Europe/Warsaw'));
        } catch (\Exception) {
            return '';
        }

        return $this->dayLabel($dt) . ', ' . $dt->format('H:i');
    }

    /**
     * @param list<array<string, mixed>> $slots
     * @return list<array{label: string, slots: list<array<string, mixed>>}>
     */
    private function groupDays(array $slots): array
    {
        $tz = new \DateTimeZone('Europe/Warsaw');
        $grouped = [];

        foreach ($slots as $slot) {
            if (!\is_array($slot) || !\is_string($slot['start'] ?? null)) {
                continue;
            }

            try {
                $start = (new \DateTimeImmutable($slot['start']))->setTimezone($tz);
                $end = \is_string($slot['end'] ?? null)
                    ? (new \DateTimeImmutable($slot['end']))->setTimezone($tz)
                    : $start->modify('+60 minutes');
            } catch (\Exception) {
                continue;
            }

            $horses = [];
            foreach ($slot['horses'] ?? [] as $horse) {
                if (!\is_array($horse) || !isset($horse['id'], $horse['name'])) {
                    continue;
                }
                $horses[] = ['id' => (string) $horse['id'], 'name' => (string) $horse['name']];
            }

            $key = $start->format('Y-m-d');
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'date' => $key,
                    'label' => $this->dayLabel($start),
                    'weekday' => $this->weekdayName($start),
                    'dayNum' => $start->format('j'),
                    'monthShort' => $this->monthShort($start),
                    'slots' => [],
                ];
            }
            $grouped[$key]['slots'][] = [
                'start' => $slot['start'],
                'time' => $start->format('H:i'),
                'timeEnd' => $end->format('H:i'),
                'horses' => $horses,
                'horseIds' => implode(',', array_column($horses, 'id')),
                'horseNames' => implode(', ', array_column($horses, 'name')),
            ];
        }

        return array_values($grouped);
    }

    /**
     * @param list<array{label: string, slots: list<array<string, mixed>>}> $days
     * @return list<array{id: string, name: string}>
     */
    private function horsesIn(array $days): array
    {
        $horses = [];
        foreach ($days as $day) {
            foreach ($day['slots'] as $slot) {
                foreach ($slot['horses'] as $horse) {
                    $horses[$horse['id']] = $horse;
                }
            }
        }

        $list = array_values($horses);
        if (class_exists(\Collator::class)) {
            $collator = new \Collator('pl_PL');
            usort($list, fn (array $a, array $b) => $collator->compare($a['name'], $b['name']));
        }

        return $list;
    }

    private function dayLabel(\DateTimeImmutable $dt): string
    {
        $months = [
            1 => 'stycznia', 'lutego', 'marca', 'kwietnia', 'maja', 'czerwca',
            'lipca', 'sierpnia', 'września', 'października', 'listopada', 'grudnia',
        ];

        return $this->weekdayName($dt) . ' ' . $dt->format('j') . ' ' . $months[(int) $dt->format('n')];
    }

    private function weekdayName(\DateTimeImmutable $dt): string
    {
        return ['niedziela', 'poniedziałek', 'wtorek', 'środa', 'czwartek', 'piątek', 'sobota'][(int) $dt->format('w')];
    }

    private function monthShort(\DateTimeImmutable $dt): string
    {
        return [
            1 => 'sty', 'lut', 'mar', 'kwi', 'maj', 'cze',
            'lip', 'sie', 'wrz', 'paź', 'lis', 'gru',
        ][(int) $dt->format('n')];
    }
}
