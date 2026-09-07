export type ScanStatus = 'pending' | 'cloning' | 'scanning' | 'completed' | 'failed';

export interface LanguageStat {
  files: number;
  lines: number;
}

export interface RepositoryInventory {
  totalFiles: number;
  totalDirectories: number;
  totalSizeBytes: number;
  binaryFileCount: number;
  ignoredFileCount: number;
  languageStats: Record<string, LanguageStat>;
  extensionStats: Record<string, number>;
  metadata: Record<string, unknown>;
  generatedAt: string;
}

export interface RepositoryScan {
  id: string;
  repositoryId: string;
  status: ScanStatus;
  commitSha: string | null;
  startedAt: string | null;
  finishedAt: string | null;
  errorMessage: string | null;
  createdAt: string;
  inventory: RepositoryInventory | null;
  auditId: string | null;
}
