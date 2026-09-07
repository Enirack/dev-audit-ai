import { Component, ElementRef, inject, signal, viewChild } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { forkJoin } from 'rxjs';
import { AiService } from '../../core/services/ai.service';
import { AuditService } from '../../core/services/audit.service';
import { Audit } from '../../core/models/audit.model';
import { EmptyState } from '../../shared/ui/empty-state/empty-state';

type LoadState = 'loading' | 'ok' | 'error';

interface DisplayMessage {
  role: 'user' | 'assistant';
  content: string;
  referencedFiles?: string[];
  referencedFindings?: string[];
  insufficientContext?: boolean;
}

@Component({
  selector: 'app-ai-assistant',
  imports: [FormsModule, RouterLink, EmptyState],
  templateUrl: './ai-assistant.html',
  styleUrl: './ai-assistant.scss',
})
export class AiAssistant {
  private readonly route = inject(ActivatedRoute);
  private readonly aiService = inject(AiService);
  private readonly auditService = inject(AuditService);

  private readonly auditId = this.route.snapshot.paramMap.get('id')!;
  private readonly scrollAnchor = viewChild<ElementRef<HTMLDivElement>>('scrollAnchor');

  protected readonly state = signal<LoadState>('loading');
  protected readonly audit = signal<Audit | null>(null);
  protected readonly messages = signal<DisplayMessage[]>([]);
  protected readonly question = signal('');
  protected readonly asking = signal(false);
  protected readonly askError = signal<string | null>(null);

  protected readonly suggestedQuestions = [
    'What are the biggest security risks?',
    'Which files should I refactor first?',
    'Explain the architecture.',
  ];

  constructor() {
    forkJoin({
      audit: this.auditService.get(this.auditId),
      history: this.aiService.chatHistory(this.auditId),
    }).subscribe({
      next: ({ audit, history }) => {
        this.audit.set(audit);
        this.messages.set(history.map((m) => ({ role: m.role, content: m.content })));
        this.state.set('ok');
        this.scrollToBottom();
      },
      error: () => this.state.set('error'),
    });
  }

  ask(): void {
    const question = this.question().trim();
    if (!question || this.asking()) return;

    this.messages.update((messages) => [...messages, { role: 'user', content: question }]);
    this.question.set('');
    this.asking.set(true);
    this.askError.set(null);
    this.scrollToBottom();

    this.aiService.ask(this.auditId, question).subscribe({
      next: (answer) => {
        this.messages.update((messages) => [
          ...messages,
          {
            role: 'assistant',
            content: answer.ai_interpretation.answer,
            referencedFiles: answer.referenced_files,
            referencedFindings: answer.referenced_findings,
            insufficientContext: answer.insufficient_context,
          },
        ]);
        this.asking.set(false);
        this.scrollToBottom();
      },
      error: () => {
        this.asking.set(false);
        this.askError.set('Unable to reach the AI assistant right now. Please try again.');
      },
    });
  }

  askSuggested(question: string): void {
    this.question.set(question);
    this.ask();
  }

  private scrollToBottom(): void {
    queueMicrotask(() => this.scrollAnchor()?.nativeElement.scrollIntoView({ behavior: 'smooth' }));
  }
}
