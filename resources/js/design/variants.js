import { colors } from './colors';
import { layers } from './layers';
import { radius } from './radius';
import { spacing } from './spacing';
import { interaction, motion } from './tokens';

export const buttonVariants = {
    primary: `${colors.emerald.solid} ${colors.emerald.solidHover}`,
    secondary: `${colors.zinc.borderStrong} ${colors.zinc.surface} ${colors.zinc.text} enabled:hover:bg-zinc-800`,
    ghost: 'bg-transparent text-zinc-300 enabled:hover:bg-zinc-800 enabled:hover:text-white',
    danger: `${colors.red.border} ${colors.red.solid} ${colors.red.hover}`,
};

export const buttonSizes = {
    sm: `${spacing.button.sm} text-sm`,
    md: `${spacing.button.md} text-sm`,
    lg: `${spacing.button.lg} text-base`,
};

export const iconButtonSizes = {
    sm: 'h-9 w-9',
    md: 'h-10 w-10',
    lg: 'h-12 w-12',
};

export const cardVariants = layers.card;

export const selectVariants = {
    field: `${spacing.button.md} ${radius.md} border ${colors.zinc.borderStrong} ${colors.zinc.surface} font-semibold ${colors.zinc.text} ${motion.standard} ${interaction.focus}`,
};

export const emptyStateVariants = layers.emptyState;

export const modalVariants = layers.modal;
