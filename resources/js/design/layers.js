import { colors } from './colors';
import { radius } from './radius';

export const layers = {
    card: {
        surface: `overflow-hidden ${radius.xl} border ${colors.zinc.border} ${colors.zinc.surfaceMuted}`,
        elevated: `overflow-hidden ${radius.xl} border ${colors.zinc.border} bg-zinc-950/80 shadow-lg shadow-zinc-950/20`,
        inset: `overflow-hidden ${radius.lg} border ${colors.zinc.border} ${colors.zinc.surfaceInset}`,
    },
    emptyState: {
        surface: `${radius.lg} border border-dashed ${colors.zinc.border} ${colors.zinc.surfaceInset} p-5 ${colors.zinc.textMuted}`,
    },
    modal: {
        overlay: 'fixed inset-0 z-50 grid place-items-center bg-zinc-950/80 px-4 backdrop-blur-sm',
        panel: `${radius.xxl} border ${colors.zinc.border} bg-zinc-900 shadow-2xl`,
        sizes: {
            sm: 'max-w-md',
            md: 'max-w-lg',
            lg: 'max-w-xl',
            xl: 'max-w-2xl',
            '2xl': 'max-w-4xl',
        },
    },
};
