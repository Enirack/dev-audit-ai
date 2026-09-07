export interface Repository {
  id: string;
  name: string;
  url: string;
  provider: string;
  defaultBranch: string | null;
  description: string | null;
  createdAt: string;
  updatedAt: string;
}

export interface CreateRepositoryRequest {
  url: string;
  description?: string;
}
