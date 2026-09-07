import { FindingCategory, FindingSeverity } from './finding.model';

export interface SeverityDistribution {
  info: number;
  low: number;
  medium: number;
  high: number;
  critical: number;
}

export interface Audit {
  id: string;
  repositoryScanId: string;
  repositoryId: string;
  repositoryName: string;
  summary: string | null;
  overallScore: number | null;
  generatedAt: string;
  severityDistribution: SeverityDistribution;
  findingsCount: number;
}

export interface AuditScore {
  category: FindingCategory;
  score: number;
}

export interface AuditStatistics {
  languages: Record<string, { files: number; lines: number }>;
  fileCount: number;
  totalSizeBytes: number;
  testDirectories: string[];
  metadataFlags: Record<string, unknown>;
  findingsByCategory: Partial<Record<FindingCategory, number>>;
  findingsBySeverity: Partial<Record<FindingSeverity, number>>;
}
