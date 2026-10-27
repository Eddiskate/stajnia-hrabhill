<?php

namespace App\Controller\Page;

use App\Service\HomeContent;
use App\Service\JazdyClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ReservationController extends AbstractController
{
    public function __construct(
        private HomeContent $home,
        private JazdyClient $jazdy,
    ) {
    }

    #[Route('/rezerwacja', name: 'page_reservation', methods: ['POST'])]
    public function create(Request $request): Response
    {
        if (trim((string) $request->request->get('company', '')) !== '') {
            return new RedirectResponse($this->generateUrl('page_homepage', ['zapisano' => '1']) . '#rezerwacja');
        }

        $values = [
            'rider_kind' => (string) $request->request->get('rider_kind', ''),
            'first_name' => trim((string) $request->request->get('first_name', '')),
            'last_name' => trim((string) $request->request->get('last_name', '')),
            'guardian_name' => trim((string) $request->request->get('guardian_name', '')),
            'phone' => trim((string) $request->request->get('phone', '')),
            'email' => trim((string) $request->request->get('email', '')),
            'start' => trim((string) $request->request->get('start', '')),
            'horse_id' => trim((string) $request->request->get('horse_id', '')),
            'note' => trim((string) $request->request->get('note', '')),
            'consent' => $request->request->get('consent') === '1',
        ];

        $errors = $this->validate($values);
        if ($errors !== []) {
            return $this->renderReservation($values, $errors);
        }

        $result = $this->jazdy->reserve([
            'riderKind' => $values['rider_kind'],
            'firstName' => $values['first_name'],
            'lastName' => $values['last_name'],
            'guardianName' => $values['rider_kind'] === 'child' ? $values['guardian_name'] : '',
            'phone' => $values['phone'],
            'email' => $values['email'],
            'start' => $values['start'],
            'horseId' => $values['horse_id'],
            'note' => $values['note'],
        ]);

        if (!($result['ok'] ?? false)) {
            $errors['form'] = (string) ($result['error'] ?? 'Nie udało się zapisać terminu.');

            return $this->renderReservation($values, $errors);
        }

        $reservation = $result['reservation'] ?? [];
        $url = $this->generateUrl('page_homepage', array_filter([
            'zapisano' => '1',
            'kon' => \is_string($reservation['horse'] ?? null) ? $reservation['horse'] : '',
            'termin' => \is_string($reservation['start'] ?? null) ? $this->home->formatWhen($reservation['start']) : '',
        ]));

        return new RedirectResponse($url . '#rezerwacja');
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, string>
     */
    private function validate(array $values): array
    {
        $errors = [];
        if (!\in_array($values['rider_kind'], ['child', 'adult'], true)) {
            $errors['rider_kind'] = 'Zaznacz, czy jedzie dziecko, czy osoba dorosła.';
        }
        if (!$this->validName($values['first_name']) || !$this->validName($values['last_name'])) {
            $errors['name'] = 'Podaj imię i nazwisko jeźdźca.';
        }
        if ($values['rider_kind'] === 'child' && !$this->validName($values['guardian_name'])) {
            $errors['guardian_name'] = 'Przy dziecku podaj imię i nazwisko opiekuna.';
        }
        $digits = preg_replace('/\D/', '', $values['phone']) ?? '';
        if (\strlen($digits) < 9 || \strlen($digits) > 15) {
            $errors['phone'] = 'Podaj numer telefonu.';
        }
        if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Podaj adres e-mail.';
        }
        if ($values['start'] === '') {
            $errors['start'] = 'Wybierz wolny termin.';
        }
        if ($values['horse_id'] !== '' && !preg_match('/^[a-f0-9]{24}$/i', $values['horse_id'])) {
            $errors['horse_id'] = 'Wybierz konia z listy.';
        }
        if (\strlen($values['note']) > 500) {
            $errors['note'] = 'Wiadomość jest za długa.';
        }
        if (!$values['consent']) {
            $errors['consent'] = 'Potrzebujemy zgody, żeby zapisać dane do terminarza jazd.';
        }

        return $errors;
    }

    private function validName(string $value): bool
    {
        return (bool) preg_match('/^\p{L}[\p{L}\s.\'-]{1,59}$/u', $value);
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, string> $errors
     */
    private function renderReservation(array $values, array $errors): Response
    {
        return $this->render('page/homepage/index.html.twig', array_merge($this->home->forPage(), [
            'form' => ['values' => $values, 'errors' => $errors],
        ]), new Response('', 422));
    }
}
