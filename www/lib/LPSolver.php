<?php
/**
 * Linear Programming Solver for CNDQ Shadow Price Calculation
 *
 * Implements the Simplex method using vertex enumeration for the 2-product problem:
 * - Deicer (d): requires C=0.5, N=0.3, D=0.2 per gallon, profit=$100/50gal drum = $2/gal
 * - Solvent (s): requires N=0.25, D=0.35, Q=0.4 per gallon, profit=$60/20gal drum = $3/gal
 *
 * Constraints:
 * - d >= 0, s >= 0 (non-negativity)
 * - 0.5*d <= C (Chemical C availability)
 * - 0.3*d + 0.25*s <= N (Chemical N availability)
 * - 0.2*d + 0.35*s <= D (Chemical D availability)
 * - 0.4*s <= Q (Chemical Q availability)
 *
 * Objective: Maximize profit = 2*d + 3*s
 */

class LPSolver {
    // Product recipes (per gallon of product)
    const DEICER_C = 0.5;
    const DEICER_N = 0.3;
    const DEICER_D = 0.2;
    const DEICER_Q = 0.0;
    const DEICER_PROFIT_PER_GALLON = 2.0;

    const SOLVENT_C = 0.0;
    const SOLVENT_N = 0.25;
    const SOLVENT_D = 0.35;
    const SOLVENT_Q = 0.4;
    const SOLVENT_PROFIT_PER_GALLON = 3.0;

    const EPSILON = 1e-9;

    /**
     * Solve LP problem using the Simplex Method
     * 
     * Decision Variables: x1 = Deicer, x2 = Solvent
     * Objective: Maximize 2*x1 + 3*x2
     */
    public function solve($inventory) {
        // Treat negative inventory as 0 available for production
        $C = max(0, $inventory['C']);
        $N = max(0, $inventory['N']);
        $D = max(0, $inventory['D']);
        $Q = max(0, $inventory['Q']);

        // Tableau setup: 5 rows (obj + 4 constraints), 7 columns (2 vars + 4 slacks + RHS)
        // Rows: 0=Obj, 1=C, 2=N, 3=D, 4=Q
        // Cols: 0=x1, 1=x2, 2=s1, 3=s2, 4=s3, 5=s4, 6=RHS
        $tableau = [
            [-self::DEICER_PROFIT_PER_GALLON, -self::SOLVENT_PROFIT_PER_GALLON, 0, 0, 0, 0, 0], // Obj
            [self::DEICER_C, 0, 1, 0, 0, 0, $C], // C constraint
            [self::DEICER_N, self::SOLVENT_N, 0, 1, 0, 0, $N], // N constraint
            [self::DEICER_D, self::SOLVENT_D, 0, 0, 1, 0, $D], // D constraint
            [0, self::SOLVENT_Q, 0, 0, 0, 1, $Q]  // Q constraint
        ];

        $maxIterations = 20;
        $iteration = 0;

        while ($iteration < $maxIterations) {
            // 1. Find entering variable (most negative in obj row)
            $pivotCol = -1;
            $minVal = -self::EPSILON;
            for ($j = 0; $j < 6; $j++) {
                if ($tableau[0][$j] < $minVal) {
                    $minVal = $tableau[0][$j];
                    $pivotCol = $j;
                }
            }

            // If no negative values, we found the optimum
            if ($pivotCol == -1) break;

            // 2. Find leaving variable (Minimum ratio test)
            $pivotRow = -1;
            $minRatio = INF;
            for ($i = 1; $i < 5; $i++) {
                if ($tableau[$i][$pivotCol] > self::EPSILON) {
                    $ratio = $tableau[$i][6] / $tableau[$i][$pivotCol];
                    if ($ratio < $minRatio) {
                        $minRatio = $ratio;
                        $pivotRow = $i;
                    }
                }
            }

            // If no valid pivot row, problem is unbounded
            if ($pivotRow == -1) break;

            // 3. Pivot
            $pivotVal = $tableau[$pivotRow][$pivotCol];
            
            // Normalize pivot row
            for ($j = 0; $j < 7; $j++) {
                $tableau[$pivotRow][$j] /= $pivotVal;
            }

            // Eliminate other rows
            for ($i = 0; $i < 5; $i++) {
                if ($i != $pivotRow) {
                    $factor = $tableau[$i][$pivotCol];
                    for ($j = 0; $j < 7; $j++) {
                        $tableau[$i][$j] -= $factor * $tableau[$pivotRow][$j];
                    }
                }
            }

            $iteration++;
        }

        // Extract results
        $maxProfit = $tableau[0][6];
        
        // Find basic variables to get production mix
        $deicer = 0;
        $solvent = 0;
        
        for ($j = 0; $j < 2; $j++) {
            $rowCount = 0;
            $lastRow = -1;
            for ($i = 1; $i < 5; $i++) {
                if (abs($tableau[$i][$j] - 1.0) < self::EPSILON) {
                    $rowCount++;
                    $lastRow = $i;
                } elseif (abs($tableau[$i][$j]) > self::EPSILON) {
                    $rowCount = 2; // Not a basic variable column
                }
            }
            if ($rowCount == 1) {
                if ($j == 0) $deicer = $tableau[$lastRow][6];
                if ($j == 1) $solvent = $tableau[$lastRow][6];
            }
        }

        // Shadow prices are in the objective row under the slack columns
        // Slack columns are index 2, 3, 4, 5 corresponding to C, N, D, Q
        $shadowPrices = [
            'C' => round($tableau[0][2], 2),
            'N' => round($tableau[0][3], 2),
            'D' => round($tableau[0][4], 2),
            'Q' => round($tableau[0][5], 2)
        ];

        // Slack Values (Excess)
        // Check if slack variables (cols 2-5) are in the basis
        $slacks = ['C' => 0, 'N' => 0, 'D' => 0, 'Q' => 0];
        $slackCols = [2 => 'C', 3 => 'N', 4 => 'D', 5 => 'Q'];

        foreach ($slackCols as $col => $chem) {
            $rowCount = 0;
            $lastRow = -1;
            for ($i = 1; $i < 5; $i++) {
                if (abs($tableau[$i][$col] - 1.0) < self::EPSILON) {
                    $rowCount++;
                    $lastRow = $i;
                } elseif (abs($tableau[$i][$col]) > self::EPSILON) {
                    $rowCount = 2; // Not a basic variable column
                }
            }
            if ($rowCount == 1 && $lastRow != -1) {
                // Slack variable is in basis, value is RHS
                $slacks[$chem] = round($tableau[$lastRow][6], 2);
            }
        }

        // Constraint Status
        $constraints = [];
        foreach ($slacks as $chem => $slack) {
            $constraints[$chem] = [
                'slack' => $slack,
                'status' => ($slack < 0.01) ? 'Binding' : 'Not Binding' // Tolerance for float math
            ];
        }

        // Sensitivity Analysis (Ranging)
        // For each constraint, find the allowable increase/decrease for the RHS
        // This uses the final tableau: RHS_new = RHS_old + B^-1 * delta
        $ranges = [];
        $slackToChem = [2 => 'C', 3 => 'N', 4 => 'D', 5 => 'Q'];
        
        foreach ($slackToChem as $colIdx => $chem) {
            $minDelta = -INF;
            $maxDelta = INF;
            
            for ($i = 1; $i < 5; $i++) {
                $rhs = $tableau[$i][6];
                $val = $tableau[$i][$colIdx];
                
                if ($val > self::EPSILON) {
                    // delta >= -rhs/val
                    $minDelta = max($minDelta, -$rhs / $val);
                } elseif ($val < -self::EPSILON) {
                    // delta <= -rhs/val
                    $maxDelta = min($maxDelta, -$rhs / $val);
                }
            }
            
            $ranges[$chem] = [
                'allowableDecrease' => ($minDelta === -INF) ? $inventory[$chem] : round(abs($minDelta), 2),
                'allowableIncrease' => ($maxDelta === INF) ? 9999 : round($maxDelta, 2)
            ];
        }

        return [
            'maxProfit' => round($maxProfit, 2),
            'deicer' => floor($deicer * 100) / 100,
            'solvent' => floor($solvent * 100) / 100,
            'shadowPrices' => $shadowPrices,
            'constraints' => $constraints,
            'ranges' => $ranges
        ];
    }

    /**
     * Interface-compatible method to get shadow prices
     */
    public function getShadowPrices($inventory) {
        $result = $this->solve($inventory);
        
        return [
            'shadowPrices' => $result['shadowPrices'],
            'ranges' => $result['ranges'],
            'constraints' => $result['constraints'],
            'optimalMix' => [
                'deicer' => $result['deicer'],
                'solvent' => $result['solvent']
            ],
            'maxProfit' => $result['maxProfit']
        ];
    }
}
