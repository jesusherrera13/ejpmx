<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Genera el rol de juegos de un torneo de béisbol todos contra todos.
 *
 * Basado en LibreriaController::calendarioAlgoritmo (método del círculo con descanso
 * cuando hay número impar de equipos), con estas mejoras:
 *  - Local y visitante se alternan jornada a jornada para que ningún equipo
 *    acumule largas rachas en casa o de gira.
 *  - En las vueltas pares se invierte la localía de la vuelta anterior.
 *  - Cada serie se expande en N partidos con fecha: las series de una jornada se juegan
 *    en paralelo y entre jornadas puede haber días de descanso.
 */
class RoundRobinScheduler
{
    /**
     * @param  array<int>  $teamIds  Equipos en el orden en que se inscribieron
     * @return array{games: array<int, array<string, mixed>>, byes: array<int, int>}
     *         games: partidos listos para insertar; byes: jornada => equipo que descansa
     */
    public function generate(
        array $teamIds,
        int $rounds,
        int $gamesPerSeries,
        CarbonImmutable $startDate,
        int $restDays = 0
    ): array {
        $matchdays = $this->singleRound(array_values($teamIds));
        $perRound = count($matchdays);

        $games = [];
        $byes = [];
        $series = 0;
        $gameNumber = 0;

        for ($round = 1; $round <= $rounds; $round++) {
            foreach ($matchdays as $index => $matchday) {
                $number = ($round - 1) * $perRound + $index + 1;
                // Las series de la jornada arrancan el mismo día
                $firstDay = $startDate->addDays(($number - 1) * ($gamesPerSeries + $restDays));

                if ($matchday['bye'] !== null) {
                    $byes[$number] = $matchday['bye'];
                }

                foreach ($matchday['pairs'] as [$home, $away]) {
                    // Vuelta par: se invierte la localía
                    if ($round % 2 === 0) {
                        [$home, $away] = [$away, $home];
                    }

                    $series++;

                    for ($game = 1; $game <= $gamesPerSeries; $game++) {
                        $games[] = [
                            'round' => $round,
                            'matchday' => $number,
                            'series' => $series,
                            'series_game' => $game,
                            'game_number' => ++$gameNumber,
                            'home_team_id' => $home,
                            'away_team_id' => $away,
                            'scheduled_date' => $firstDay->addDays($game - 1)->toDateString(),
                        ];
                    }
                }
            }
        }

        return ['games' => $games, 'byes' => $byes];
    }

    /**
     * Una vuelta completa por el método del círculo: el primer equipo queda fijo y el resto rota.
     * Con número impar se agrega un lugar vacío (null) en la posición fija: quien se enfrenta
     * a él descansa, y así la localía del resto queda balanceada.
     *
     * @return array<int, array{pairs: array<int, array{0: int, 1: int}>, bye: int|null}>
     */
    private function singleRound(array $teams): array
    {
        if (count($teams) % 2 !== 0) {
            array_unshift($teams, null);
        }

        $count = count($teams);
        $half = intdiv($count, 2);
        $matchdays = [];

        for ($day = 0; $day < $count - 1; $day++) {
            $pairs = [];
            $bye = null;

            for ($i = 0; $i < $half; $i++) {
                $home = $teams[$i];
                $away = $teams[$count - 1 - $i];

                // Alternar localía: el equipo fijo cambia cada jornada y el resto según su posición
                if (($i === 0 && $day % 2 === 1) || ($i > 0 && $i % 2 === 1)) {
                    [$home, $away] = [$away, $home];
                }

                if ($home === null || $away === null) {
                    $bye = $home ?? $away;
                    continue;
                }

                $pairs[] = [$home, $away];
            }

            $matchdays[] = ['pairs' => $pairs, 'bye' => $bye];

            // Rotar todos excepto el primero
            $last = array_pop($teams);
            array_splice($teams, 1, 0, [$last]);
        }

        return $matchdays;
    }
}
