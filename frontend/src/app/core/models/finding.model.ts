export type FindingSeverity = 'info' | 'low' | 'medium' | 'high' | 'critical';

export type FindingCategory =
  'security' | 'architecture' | 'maintainability' | 'performance' | 'testing' | 'code_quality';

export interface AuditFinding {
  id: string;
  ruleId: string;
  category: FindingCategory;
  severity: FindingSeverity;
  priority: number;
  confidence: number | null;
  title: string;
  description: string;
  filePath: string | null;
  startLine: number | null;
  endLine: number | null;
  recommendation: string | null;
  analyzer: string;
  metadata: Record<string, unknown> | null;
  createdAt: string;
}

export interface PaginatedFindings {
  data: AuditFinding[];
  page: number;
  perPage: number;
  total: number;
  totalPages: number;
}

export interface FindingFilters {
  category?: FindingCategory;
  severity?: FindingSeverity;
  file?: string;
  rule?: string;
  page?: number;
  perPage?: number;
}
