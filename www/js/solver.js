import { RECIPES } from './config.js';

function solveLP(inventory) {
    // Constraints
    // d >= 0, s >= 0
    // 0.5d <= C
    // 0.3d + 0.25s <= N
    // 0.2d + 0.35s <= D
    // 0.4s <= Q

    const C = inventory.C;
    const N = inventory.N;
    const D = inventory.D;
    const Q = inventory.Q;

    // Define lines as ax + by = c (where x=d, y=s)
    const lines = [
        { a: 1, b: 0, c: 0 }, // d = 0 (Lower bound)
        { a: 0, b: 1, c: 0 }, // s = 0 (Lower bound)
        { a: 0.5, b: 0, c: C }, // C constraint
        { a: 0.3, b: 0.25, c: N }, // N constraint
        { a: 0.2, b: 0.35, c: D }, // D constraint
        { a: 0, b: 0.4, c: Q }  // Q constraint
    ];

    // Find intersections
    let points = [];
    
    for (let i = 0; i < lines.length; i++) {
        for (let j = i + 1; j < lines.length; j++) {
            const l1 = lines[i];
            const l2 = lines[j];
            
            // Cramer's rule / intersection of two lines
            const det = l1.a * l2.b - l2.a * l1.b;
            if (Math.abs(det) < 1e-9) continue; // Parallel lines

            const d = (l1.c * l2.b - l2.c * l1.b) / det;
            const s = (l1.a * l2.c - l2.a * l1.c) / det;

            points.push({ d, s });
        }
    }

    // Filter valid points
    const validPoints = points.filter(p => {
        if (p.d < -1e-5 || p.s < -1e-5) return false;
        
        // Check constraints with small epsilon tolerance
        const checkC = (0.5 * p.d) <= C + 1e-5;
        const checkN = (0.3 * p.d + 0.25 * p.s) <= N + 1e-5;
        const checkD = (0.2 * p.d + 0.35 * p.s) <= D + 1e-5;
        const checkQ = (0.4 * p.s) <= Q + 1e-5;

        return checkC && checkN && checkD && checkQ;
    });

    // Find max profit
    let maxProfit = -1;
    let bestMix = { d: 0, s: 0 };

    validPoints.forEach(p => {
        const profit = (p.d * RECIPES.deicer.profit) + (p.s * RECIPES.solvent.profit);
        if (profit > maxProfit) {
            maxProfit = profit;
            bestMix = p;
        }
    });

    return { maxProfit, d: bestMix.d, s: bestMix.s };
}

export function getShadowPrices(inventory) {
    const baseline = solveLP(inventory);
    const delta = 1; // Small increment (1 gallon)

    const prices = {};
    ['C', 'N', 'D', 'Q'].forEach(chem => {
        const newInv = { ...inventory, [chem]: inventory[chem] + delta };
        const newRes = solveLP(newInv);
        prices[chem] = (newRes.maxProfit - baseline.maxProfit) / delta;
    });

    return {
        optimalMix: { deicer: baseline.d, solvent: baseline.s },
        maxProfit: baseline.maxProfit,
        shadowPrices: prices
    };
}
