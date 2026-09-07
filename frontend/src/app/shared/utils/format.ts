import { FindingCategory, FindingSeverity } from '../../core/models/finding.model';
import { ScanStatus } from '../../core/models/scan.model';

const CATEGORY_LABELS: Record<FindingCategory, string> = {
  security: 'Security',
  architecture: 'Architecture',
  maintainability: 'Maintainability',
  performance: 'Performance',
  testing: 'Testing',
  code_quality: 'Code Quality',
};

const SEVERITY_LABELS: Record<FindingSeverity, string> = {
  critical: 'Critical',
  high: 'High',
  medium: 'Medium',
  low: 'Low',
  info: 'Info',
};

const SCAN_STATUS_LABELS: Record<ScanStatus, string> = {
  pending: 'Pending',
  cloning: 'Cloning',
  scanning: 'Scanning',
  completed: 'Completed',
  failed: 'Failed',
};

export function categoryLabel(category: FindingCategory): string {
  return CATEGORY_LABELS[category] ?? category;
}

export function severityLabel(severity: FindingSeverity): string {
  return SEVERITY_LABELS[severity] ?? severity;
}

export function scanStatusLabel(status: ScanStatus): string {
  return SCAN_STATUS_LABELS[status] ?? status;
}

/** Score color thresholds shared by every score display (gauge, bars, dashboard cards). */
export function scoreColor(score: number | null): string {
  if (score === null) return 'var(--color-text-faint)';
  if (score >= 80) return 'var(--color-success)';
  if (score >= 60) return 'var(--color-warning)';
  if (score >= 40) return 'var(--sev-high)';
  return 'var(--color-danger)';
}

export function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  const units = ['KB', 'MB', 'GB', 'TB'];
  let value = bytes / 1024;
  let unitIndex = 0;
  while (value >= 1024 && unitIndex < units.length - 1) {
    value /= 1024;
    unitIndex += 1;
  }
  return `${value.toFixed(value >= 10 ? 0 : 1)} ${units[unitIndex]}`;
}

export function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

export function formatDateTime(iso: string): string {
  return new Date(iso).toLocaleString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

const LANGUAGE_PALETTE = ['#7c6cf6', '#22d3ee', '#f97316', '#22c55e', '#eab308', '#ec4899', '#3b82f6', '#a3e635'];

export function languageColor(index: number): string {
  return LANGUAGE_PALETTE[index % LANGUAGE_PALETTE.length];
}

export function relativeTime(iso: string): string {
  const diffMs = Date.now() - new Date(iso).getTime();
  const diffMinutes = Math.round(diffMs / 60000);

  if (diffMinutes < 1) return 'just now';
  if (diffMinutes < 60) return `${diffMinutes}m ago`;
  const diffHours = Math.round(diffMinutes / 60);
  if (diffHours < 24) return `${diffHours}h ago`;
  const diffDays = Math.round(diffHours / 24);
  if (diffDays < 30) return `${diffDays}d ago`;
  return formatDate(iso);
}
