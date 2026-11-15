/**
 * The indexes of a longest strictly increasing subsequence of the values, in O(n log n). The blocks at those indexes
 * keep their order, so only the others need moving.
 */
export function longestIncreasing(values: readonly number[]): Set<number> {
    // tails[n] is the index of the smallest value that ends an increasing subsequence of length n + 1.
    const tails: number[] = [];
    const previous: number[] = [];

    values.forEach((value, i) => {
        let low = 0;
        let high = tails.length;
        while (low < high) {
            const middle = (low + high) >> 1;
            if ((values[tails[middle] ?? 0] ?? 0) < value) {
                low = middle + 1;
            } else {
                high = middle;
            }
        }

        previous[i] = low > 0 ? (tails[low - 1] ?? -1) : -1;
        tails[low] = i;
    });

    const indexes = new Set<number>();
    for (let i = tails[tails.length - 1] ?? -1; i !== -1; i = previous[i] ?? -1) {
        indexes.add(i);
    }

    return indexes;
}
