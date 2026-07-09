import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Router } from '@angular/router';
import { timeout, catchError } from 'rxjs/operators';
import { of } from 'rxjs';

interface PlatformCred {
  platform: string;
  is_configured: boolean;
  is_verified: boolean;
  connected_username: string | null;
  last_verified_at: string | null;
  has_api_key: boolean;
  has_api_secret: boolean;
  has_access_token: boolean;
  has_access_token_secret: boolean;
  has_bearer_token: boolean;
  has_page_access_token: boolean;
  page_id: string | null;
  li_person_urn: string | null;
}

interface PlatformConfig {
  platform: string;
  label: string;
  icon: string;
  color: string;
  gradient: string;
  fields: FieldDef[];
  guideUrl: string;
  guideSteps: string[];
}

interface FieldDef {
  key: string;
  label: string;
  placeholder: string;
  type: 'text' | 'password';
  hint: string;
}

@Component({
  selector: 'app-connect-platforms',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div class="cp-container">
      <!-- Header -->
      <div class="cp-header">
        <button class="back-btn" (click)="goBack()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
          Back to Dashboard
        </button>
        <div class="header-content">
          <h1>Connect Platforms</h1>
          <p>Enter your API credentials to enable posting. Keys are encrypted with AES-256 and stored securely.</p>
        </div>
      </div>

      <!-- Loading -->
      <div *ngIf="loading" class="loading-state">
        <div class="spinner"></div>
        <p>Loading credential status...</p>
      </div>

      <!-- Platform Cards -->
      <div *ngIf="!loading" class="platforms-grid">
        <div
          *ngFor="let platform of platforms"
          class="platform-card"
          [class.verified]="credMap[platform.platform]?.is_verified"
          [class.configured]="credMap[platform.platform]?.is_configured && !credMap[platform.platform]?.is_verified"
          [style.--platform-color]="platform.color"
          [style.--platform-gradient]="platform.gradient"
        >
          <!-- Card Header -->
          <div class="card-header">
            <div class="platform-icon" [innerHTML]="platform.icon"></div>
            <div class="platform-info">
              <h3>{{ platform.label }}</h3>
              <div class="status-badge" [class.verified]="credMap[platform.platform]?.is_verified"
                   [class.configured]="credMap[platform.platform]?.is_configured && !credMap[platform.platform]?.is_verified"
                   [class.not-configured]="!credMap[platform.platform]?.is_configured">
                <span *ngIf="credMap[platform.platform]?.is_verified">✅ Connected as @{{ credMap[platform.platform]?.connected_username }}</span>
                <span *ngIf="credMap[platform.platform]?.is_configured && !credMap[platform.platform]?.is_verified">⚠️ Credentials saved, not verified</span>
                <span *ngIf="!credMap[platform.platform]?.is_configured">🔴 Not connected</span>
              </div>
            </div>
            <div class="card-actions">
              <button
                *ngIf="credMap[platform.platform]?.is_configured"
                class="btn btn-sm btn-danger"
                (click)="disconnectPlatform(platform.platform)"
                [disabled]="actionLoading[platform.platform]"
              >Remove</button>
              <button
                class="btn btn-sm btn-secondary"
                (click)="toggleExpanded(platform.platform)"
              >{{ expanded[platform.platform] ? 'Hide' : (credMap[platform.platform]?.is_configured ? 'Edit' : 'Connect') }}</button>
            </div>
          </div>

          <!-- Expandable Form -->
          <div class="card-body" *ngIf="expanded[platform.platform]">

            <!-- How to get keys guide -->
            <div class="guide-box">
              <div class="guide-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
                How to get your {{ platform.label }} credentials
              </div>
              <ol class="guide-steps">
                <li *ngFor="let step of platform.guideSteps">{{ step }}</li>
              </ol>
              <a [href]="platform.guideUrl" target="_blank" class="guide-link">
                Open {{ platform.label }} Developer Portal →
              </a>
            </div>

            <!-- Fields -->
            <div class="fields-grid">
              <div class="field-group" *ngFor="let field of platform.fields">
                <label>{{ field.label }}</label>
                <div class="input-wrapper">
                  <input
                    [type]="field.type === 'password' && !showFields[platform.platform + '_' + field.key] ? 'password' : 'text'"
                    [placeholder]="field.placeholder"
                    [(ngModel)]="formData[platform.platform][field.key]"
                    class="form-input"
                  />
                  <button
                    *ngIf="field.type === 'password'"
                    class="toggle-vis"
                    type="button"
                    (click)="toggleFieldVisibility(platform.platform, field.key)"
                  >{{ showFields[platform.platform + '_' + field.key] ? '🙈' : '👁️' }}</button>
                </div>
                <small class="field-hint">{{ field.hint }}</small>
              </div>
            </div>

            <!-- One-click Connect -->
            <div class="form-actions">
              <button
                class="btn btn-primary"
                (click)="saveAndConnect(platform.platform)"
                [disabled]="actionLoading[platform.platform]"
              >
                <span *ngIf="!actionLoading[platform.platform]">
                  {{ credMap[platform.platform]?.is_verified ? '🔄 Update & Reconnect' : '🔗 Save & Connect' }}
                </span>
                <span *ngIf="actionLoading[platform.platform]">Connecting...</span>
              </button>
            </div>

            <!-- Already connected notice -->
            <div *ngIf="credMap[platform.platform]?.is_verified" class="one-time-notice">
              ✅ Connected as <strong>@{{ credMap[platform.platform]?.connected_username }}</strong> — credentials are saved permanently. No re-entry needed for future posts.
            </div>

            <!-- Result Message -->
            <div *ngIf="messages[platform.platform]" class="result-message" [class.success]="messageType[platform.platform] === 'success'" [class.error]="messageType[platform.platform] === 'error'">
              {{ messages[platform.platform] }}
            </div>
          </div>
        </div>
      </div>

      <!-- Info Box -->
      <div class="info-box">
        <h4>🔒 Security &amp; Privacy</h4>
        <p>Your API keys and tokens are encrypted using AES-256 before storage. They are never logged or exposed in API responses. You can remove credentials at any time from this page.</p>
      </div>
    </div>
  `,
  styles: [`
    :host { display: block; }

    .cp-container {
      max-width: 900px;
      margin: 0 auto;
      padding: 8px 0 32px;
      font-family: 'Inter', sans-serif;
      animation: fadeIn 0.4s ease both;
    }

    @keyframes fadeIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
    @keyframes spin { to { transform: rotate(360deg); } }

    .cp-header { margin-bottom: 36px; }

    .back-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: none;
      border: none;
      color: var(--text-muted, #7A6E65);
      cursor: pointer;
      font-size: 13px;
      font-family: 'Inter', sans-serif;
      padding: 6px 0;
      margin-bottom: 20px;
      transition: color 0.2s;
      letter-spacing: 0.02em;
    }
    .back-btn:hover { color: var(--gold, #D4A44D); }
    .back-btn svg { width: 15px; height: 15px; }

    .header-content h1 {
      font-family: 'Playfair Display', serif;
      font-size: 2.25rem;
      font-weight: 700;
      margin: 0 0 8px;
      color: var(--text-primary, #F7F3EE);
      letter-spacing: -0.025em;
      background: none;
      -webkit-text-fill-color: var(--text-primary, #F7F3EE);
    }
    .header-content p {
      color: var(--text-secondary, #B8AA9C);
      margin: 0;
      font-size: 0.875rem;
    }

    /* Loading */
    .loading-state {
      text-align: center;
      padding: 80px;
      color: var(--text-secondary, #B8AA9C);
      font-size: 0.875rem;
    }
    .spinner {
      width: 32px; height: 32px;
      border: 2px solid rgba(255,255,255,0.1);
      border-top-color: var(--gold, #D4A44D);
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
      margin: 0 auto 16px;
    }

    /* Grid */
    .platforms-grid {
      display: flex;
      flex-direction: column;
      gap: 16px;
      margin-bottom: 28px;
    }

    /* Platform Card */
    .platform-card {
      background: var(--surface, #231C16);
      border: 1px solid rgba(255,255,255,0.07);
      border-radius: 18px;
      overflow: hidden;
      transition: all 0.3s ease;
      position: relative;
    }

    .platform-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 1px;
      background: linear-gradient(90deg, transparent, rgba(212,164,77,0.15), transparent);
    }

    .platform-card:hover {
      border-color: rgba(255,255,255,0.12);
      box-shadow: 0 8px 32px rgba(0,0,0,0.4), 0 0 0 1px rgba(212,164,77,0.06);
      background: var(--surface-el, #2B221B);
      transform: translateY(-2px);
    }

    .platform-card.verified {
      border-color: rgba(62,207,142,0.25);
      box-shadow: 0 0 0 1px rgba(62,207,142,0.12), 0 8px 24px rgba(0,0,0,0.3);
    }

    .platform-card.configured {
      border-color: rgba(251,191,36,0.25);
    }

    /* Card Header Row */
    .card-header {
      display: flex;
      align-items: center;
      gap: 16px;
      padding: 20px 24px;
      background: rgba(0,0,0,0.15);
    }

    .platform-icon {
      width: 48px; height: 48px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 13px;
      background: var(--platform-gradient);
      flex-shrink: 0;
      box-shadow: 0 2px 12px rgba(0,0,0,0.3);
    }
    .platform-icon svg { width: 26px; height: 26px; }

    .platform-info { flex: 1; }
    .platform-info h3 {
      margin: 0 0 6px;
      font-size: 1rem;
      font-weight: 700;
      color: var(--text-primary, #F7F3EE);
      letter-spacing: -0.01em;
    }

    /* Status badge */
    .status-badge {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      font-size: 0.72rem;
      font-weight: 600;
      padding: 3px 10px;
      border-radius: 99px;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }
    .status-badge.verified {
      background: rgba(62,207,142,0.10);
      color: #3ECF8E;
      border: 1px solid rgba(62,207,142,0.2);
    }
    .status-badge.configured {
      background: rgba(251,191,36,0.10);
      color: #FBBF24;
      border: 1px solid rgba(251,191,36,0.2);
    }
    .status-badge.not-configured {
      background: rgba(248,113,113,0.10);
      color: #F87171;
      border: 1px solid rgba(248,113,113,0.2);
    }

    .card-actions { display: flex; gap: 8px; }

    /* Buttons */
    .btn {
      padding: 8px 16px;
      border-radius: 9px;
      border: none;
      cursor: pointer;
      font-size: 0.8rem;
      font-weight: 600;
      font-family: 'Inter', sans-serif;
      transition: all 0.2s ease;
      letter-spacing: 0.01em;
    }
    .btn:disabled { opacity: 0.5; cursor: not-allowed; }
    .btn-sm { padding: 7px 14px; font-size: 0.78rem; }

    .btn-primary {
      background: linear-gradient(135deg, #B88432, #D4A44D, #E8BE6A);
      color: #1C1612;
      font-weight: 700;
      box-shadow: 0 3px 12px rgba(212,164,77,0.25);
    }
    .btn-primary:hover:not(:disabled) {
      filter: brightness(1.1);
      transform: translateY(-1px);
      box-shadow: 0 6px 20px rgba(212,164,77,0.35);
    }

    .btn-success {
      background: rgba(62,207,142,0.15);
      color: #3ECF8E;
      border: 1px solid rgba(62,207,142,0.3);
    }
    .btn-success:hover:not(:disabled) {
      background: rgba(62,207,142,0.22);
      border-color: rgba(62,207,142,0.5);
    }

    .btn-secondary {
      background: var(--surface-el, #2B221B);
      color: var(--text-secondary, #B8AA9C);
      border: 1px solid rgba(255,255,255,0.1);
    }
    .btn-secondary:hover {
      background: var(--surface-high, #342820);
      color: var(--text-primary, #F7F3EE);
      border-color: rgba(212,164,77,0.3);
    }

    .btn-danger {
      background: rgba(248,113,113,0.10);
      color: #F87171;
      border: 1px solid rgba(248,113,113,0.25);
    }
    .btn-danger:hover:not(:disabled) {
      background: rgba(248,113,113,0.18);
      border-color: rgba(248,113,113,0.4);
    }

    /* Expandable body */
    .card-body {
      padding: 0 24px 24px;
      border-top: 1px solid rgba(255,255,255,0.06);
    }

    /* Guide box */
    .guide-box {
      background: rgba(96,165,250,0.05);
      border: 1px solid rgba(96,165,250,0.15);
      border-radius: 12px;
      padding: 16px;
      margin: 20px 0 18px;
    }
    .guide-title {
      display: flex;
      align-items: center;
      gap: 8px;
      font-weight: 600;
      font-size: 0.8rem;
      color: #60A5FA;
      margin-bottom: 10px;
      text-transform: uppercase;
      letter-spacing: 0.06em;
    }
    .guide-title svg { color: #60A5FA; flex-shrink: 0; }
    .guide-steps {
      margin: 0 0 12px;
      padding-left: 20px;
      font-size: 0.82rem;
      color: var(--text-secondary, #B8AA9C);
      line-height: 1.9;
    }
    .guide-link {
      font-size: 0.8rem;
      color: var(--gold, #D4A44D);
      text-decoration: none;
      font-weight: 600;
      transition: color 0.2s;
      border-bottom: 1px solid transparent;
    }
    .guide-link:hover {
      color: #E8BE6A;
      border-bottom-color: var(--gold, #D4A44D);
    }

    /* Fields */
    .fields-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
      margin-bottom: 20px;
    }
    @media (max-width: 600px) { .fields-grid { grid-template-columns: 1fr; } }

    .field-group label {
      display: block;
      font-size: 0.72rem;
      font-weight: 700;
      color: var(--text-secondary, #B8AA9C);
      margin-bottom: 7px;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }

    .input-wrapper { position: relative; }

    .form-input {
      width: 100%;
      padding: 11px 38px 11px 13px;
      background: var(--bg-secondary, #1C1612);
      border: 1px solid rgba(255,255,255,0.1);
      border-radius: 10px;
      font-size: 0.82rem;
      color: var(--text-primary, #F7F3EE);
      transition: all 0.25s ease;
      box-sizing: border-box;
      font-family: 'Space Grotesk', 'Courier New', monospace;
      letter-spacing: 0.02em;
    }
    .form-input::placeholder { color: rgba(122,110,101,0.7); }
    .form-input:focus {
      outline: none;
      border-color: var(--gold-dark, #B88432);
      box-shadow: 0 0 0 3px rgba(212,164,77,0.12);
      background: rgba(35,28,22,0.9);
    }

    .toggle-vis {
      position: absolute;
      right: 9px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      font-size: 14px;
      padding: 3px;
      color: var(--text-muted, #7A6E65);
      transition: color 0.2s;
      line-height: 1;
    }
    .toggle-vis:hover { color: var(--gold, #D4A44D); }

    .field-hint {
      display: block;
      font-size: 0.7rem;
      color: var(--text-muted, #7A6E65);
      margin-top: 5px;
      line-height: 1.5;
    }

    /* Form actions */
    .form-actions {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      margin-bottom: 14px;
    }

    /* Result message */
    .result-message {
      padding: 12px 16px;
      border-radius: 10px;
      font-size: 0.84rem;
      font-weight: 500;
      line-height: 1.5;
    }
    .result-message.success {
      background: rgba(62,207,142,0.08);
      color: #3ECF8E;
      border: 1px solid rgba(62,207,142,0.2);
    }
    .result-message.error {
      background: rgba(248,113,113,0.08);
      color: #F87171;
      border: 1px solid rgba(248,113,113,0.2);
    }

    /* Security info box */
    .info-box {
      background: var(--surface, #231C16);
      border: 1px solid rgba(212,164,77,0.15);
      border-radius: 16px;
      padding: 24px;
      position: relative;
      overflow: hidden;
    }
    .info-box::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 1px;
      background: linear-gradient(90deg, transparent, rgba(212,164,77,0.3), transparent);
    }
    .info-box h4 {
      margin: 0 0 10px;
      font-size: 0.9rem;
      font-weight: 700;
      color: var(--gold, #D4A44D);
      letter-spacing: 0.01em;
    }
    .info-box p {
      margin: 0;
      font-size: 0.84rem;
      color: var(--text-secondary, #B8AA9C);
      line-height: 1.7;
    }

    /* One-time setup success notice */
    .one-time-notice {
      margin-top: 12px;
      padding: 12px 16px;
      background: rgba(62,207,142,0.07);
      border: 1px solid rgba(62,207,142,0.2);
      border-radius: 10px;
      font-size: 0.82rem;
      color: #3ECF8E;
      line-height: 1.6;
    }

    .one-time-notice strong { color: #F7F3EE; font-weight: 600; }
  `]
})
export class ConnectPlatforms implements OnInit {

  loading = true;
  credMap: Record<string, PlatformCred> = {};
  expanded: Record<string, boolean> = {};
  actionLoading: Record<string, boolean> = {};
  messages: Record<string, string> = {};
  messageType: Record<string, 'success' | 'error'> = {};
  showFields: Record<string, boolean> = {};
  formData: Record<string, Record<string, string>> = {};

  platforms: PlatformConfig[] = [
    {
      platform: 'twitter',
      label: 'X (Twitter)',
      icon: `<svg viewBox="0 0 24 24" fill="white"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.748l7.73-8.835L1.254 2.25H8.08l4.259 5.63zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>`,
      color: '#000000',
      gradient: 'linear-gradient(135deg, #000000, #333333)',
      guideUrl: 'https://developer.twitter.com/en/portal/dashboard',
      guideSteps: [
        'Go to developer.twitter.com and log in',
        'Create a new App (or use existing) in the Developer Portal',
        'Set App permissions to "Read and Write"',
        'Go to "Keys and Tokens" tab',
        'Copy API Key, API Key Secret, Access Token, Access Token Secret',
      ],
      fields: [
        { key: 'api_key',             label: 'API Key (Consumer Key)',    placeholder: 'xxxxxxxxxxxxxxxxxxxxxx',                 type: 'password', hint: 'From Developer Portal > Keys and Tokens > Consumer Keys' },
        { key: 'api_secret',          label: 'API Secret (Consumer Secret)', placeholder: 'xxxxxxxxxxxxxxxxxxxxxx',              type: 'password', hint: 'The secret paired with your API Key' },
        { key: 'access_token',        label: 'Access Token',              placeholder: 'xxxxxxxx-xxxxxxxxxxxxxxxxxxxxxxxxxx',    type: 'password', hint: 'From Keys and Tokens > Authentication Tokens' },
        { key: 'access_token_secret', label: 'Access Token Secret',       placeholder: 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',     type: 'password', hint: 'The secret paired with your Access Token' },
      ]
    },
    {
      platform: 'bluesky',
      label: 'Bluesky',
      icon: `<svg viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg"><path d="M12 10.8C10.8 8.4 7.8 3.6 5.4 1.8 3 0 1.8 1.2 1.8 3.6c0 1.2.6 4.8.9 5.4.9 3 3.6 3.9 6 3.3-.3.3-1.8 2.4 1.2 4.2 3.6 2.1 5.1-1.2 5.1-1.2s1.5 3.3 5.1 1.2c3-1.8 1.5-3.9 1.2-4.2 2.4.6 5.1-.3 6-3.3.3-.6.9-4.2.9-5.4C28.2 1.2 27 0 24.6 1.8 22.2 3.6 19.2 8.4 18 10.8Z"/></svg>`,
      color: '#0085ff',
      gradient: 'linear-gradient(135deg, #0085ff, #00c6ff)',
      guideUrl: 'https://bsky.app',
      guideSteps: [
        'Create a free account at bsky.app',
        'Go to Settings → Privacy & Security',
        'Click "App Passwords" → "Add App Password"',
        'Name it anything (e.g. PostScheduler) and copy the generated password',
        'Your handle is your Bluesky username (e.g. yourname.bsky.social)',
      ],
      fields: [
        { key: 'api_key',    label: 'Bluesky Handle', placeholder: 'yourname.bsky.social', type: 'text',     hint: 'Your full Bluesky handle (e.g. yourname.bsky.social)' },
        { key: 'api_secret', label: 'App Password',   placeholder: 'xxxx-xxxx-xxxx-xxxx', type: 'password', hint: 'Generate one in Settings > Privacy & Security > App Passwords' },
      ]
    },
    {
      platform: 'reddit',
      label: 'Reddit',
      icon: `<svg viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg"><path d="M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 0 1 4.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 0 1 .14-.197.35.35 0 0 1 .238-.042l2.906.617a1.214 1.214 0 0 1 1.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 0 0-.231.094.33.33 0 0 0 0 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 0 0 .029-.463.33.33 0 0 0-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 0 0-.232-.095z"/></svg>`,
      color: '#ff4500',
      gradient: 'linear-gradient(135deg, #ff4500, #ff6534)',
      guideUrl: 'https://www.reddit.com/prefs/apps',
      guideSteps: [
        'Go to reddit.com/prefs/apps and log in',
        'Scroll to bottom → Click "Create App"',
        'Name: PostScheduler | Type: ✅ script | Redirect URI: http://localhost:8080',
        'Click Create App → Copy the Client ID (under app name) and Client Secret',
        'Use your Reddit username and password in the fields below',
        'For subreddit, enter any subreddit you can post to (e.g. test)',
      ],
      fields: [
        { key: 'api_key',             label: 'Client ID',       placeholder: 'abc123xyz',        type: 'password', hint: 'Short string under your app name in reddit.com/prefs/apps' },
        { key: 'api_secret',          label: 'Client Secret',   placeholder: 'xxxxxxxxxxxxxxxxx', type: 'password', hint: 'Labeled "secret" on your Reddit app page' },
        { key: 'access_token',        label: 'Reddit Username', placeholder: 'your_username',    type: 'text',     hint: 'Your Reddit account username (without u/)' },
        { key: 'access_token_secret', label: 'Reddit Password', placeholder: '••••••••',         type: 'password', hint: 'Your Reddit account password' },
        { key: 'page_id',             label: 'Subreddit',       placeholder: 'test',             type: 'text',     hint: 'Subreddit to post to (without r/). Use "test" for testing.' },
      ]
    }
  ];

  constructor(
    private http: HttpClient,
    private router: Router,
    private cdr: ChangeDetectorRef
  ) {
    // Initialize formData
    this.platforms.forEach(p => {
      this.formData[p.platform] = {};
      p.fields.forEach(f => this.formData[p.platform][f.key] = '');
    });
  }

  ngOnInit(): void {
    this.loadCredentials();
  }

  loadCredentials(): void {
    this.loading = true;
    this.cdr.detectChanges();

    const token = localStorage.getItem('auth_token');
    if (!token) {
      // No token — redirect to login
      this.router.navigate(['/login']);
      return;
    }

    const headers = new HttpHeaders({ Authorization: 'Bearer ' + token });

    this.http.get<any>('/api/credentials', { headers })
      .pipe(
        timeout(10000),
        catchError((err) => {
          console.error('loadCredentials error:', err);
          // Return safe default so spinner always stops
          return of({ status: 'error', credentials: {} });
        })
      )
      .subscribe({
        next: (res) => {
          if (res && res.credentials) {
            this.credMap = res.credentials;
          } else {
            // Safe default: all platforms unconfigured
            this.credMap = {
              twitter:   { platform: 'twitter',   is_configured: false, is_verified: false, connected_username: null, last_verified_at: null, has_api_key: false, has_api_secret: false, has_access_token: false, has_access_token_secret: false, has_bearer_token: false, has_page_access_token: false, page_id: null, li_person_urn: null },
              instagram: { platform: 'instagram', is_configured: false, is_verified: false, connected_username: null, last_verified_at: null, has_api_key: false, has_api_secret: false, has_access_token: false, has_access_token_secret: false, has_bearer_token: false, has_page_access_token: false, page_id: null, li_person_urn: null },
              facebook:  { platform: 'facebook',  is_configured: false, is_verified: false, connected_username: null, last_verified_at: null, has_api_key: false, has_api_secret: false, has_access_token: false, has_access_token_secret: false, has_bearer_token: false, has_page_access_token: false, page_id: null, li_person_urn: null },
              linkedin:  { platform: 'linkedin',  is_configured: false, is_verified: false, connected_username: null, last_verified_at: null, has_api_key: false, has_api_secret: false, has_access_token: false, has_access_token_secret: false, has_bearer_token: false, has_page_access_token: false, page_id: null, li_person_urn: null },
              bluesky:   { platform: 'bluesky',   is_configured: false, is_verified: false, connected_username: null, last_verified_at: null, has_api_key: false, has_api_secret: false, has_access_token: false, has_access_token_secret: false, has_bearer_token: false, has_page_access_token: false, page_id: null, li_person_urn: null },
              reddit:    { platform: 'reddit',    is_configured: false, is_verified: false, connected_username: null, last_verified_at: null, has_api_key: false, has_api_secret: false, has_access_token: false, has_access_token_secret: false, has_bearer_token: false, has_page_access_token: false, page_id: null, li_person_urn: null },
            };
          }
          this.loading = false;
          this.cdr.detectChanges(); // <-- CRITICAL: trigger re-render in zoneless mode
        },
        error: (err) => {
          console.error('Credential fetch failed:', err);
          this.loading = false;
          this.cdr.detectChanges(); // <-- also needed in error path
        }
      });
  }

  toggleExpanded(platform: string): void {
    this.expanded[platform] = !this.expanded[platform];
    this.messages[platform] = '';
  }

  toggleFieldVisibility(platform: string, field: string): void {
    const key = platform + '_' + field;
    this.showFields[key] = !this.showFields[key];
  }

  /** Save credentials then immediately verify — single-click connect flow */
  saveAndConnect(platform: string): void {
    this.actionLoading[platform] = true;
    this.messages[platform] = 'Saving credentials...';
    this.messageType[platform] = 'success';
    this.cdr.detectChanges();

    const saveHeaders = new HttpHeaders({
      Authorization: 'Bearer ' + localStorage.getItem('auth_token'),
      'Content-Type': 'application/json'
    });

    const data = this.formData[platform];

    // Step 1: Save
    this.http.post<any>(`/api/credentials/${platform}`, data, { headers: saveHeaders })
      .pipe(timeout(10000), catchError(err => of({ error: true, _err: err })))
      .subscribe({
        next: (saveRes) => {
          if (saveRes?.error) {
            const err = saveRes._err;
            const msg = err?.error?.message || (err?.error?.errors ? Object.values(err.error.errors).flat().join(', ') : 'Failed to save credentials.');
            this.messages[platform] = typeof msg === 'string' ? msg : 'Validation error.';
            this.messageType[platform] = 'error';
            this.actionLoading[platform] = false;
            this.cdr.detectChanges();
            return;
          }

          // Step 2: Auto-verify immediately
          this.messages[platform] = 'Credentials saved — connecting now...';
          this.cdr.detectChanges();

          const verifyHeaders = new HttpHeaders({ Authorization: 'Bearer ' + localStorage.getItem('auth_token') });

          this.http.post<any>(`/api/credentials/${platform}/verify`, {}, { headers: verifyHeaders })
            .pipe(timeout(20000), catchError(err => of({ error: true, _err: err })))
            .subscribe({
              next: (verifyRes) => {
                this.actionLoading[platform] = false;
                if (verifyRes?.error) {
                  const errMsg = verifyRes._err?.error?.message || 'Credentials saved but connection test failed. Check your handle/password.';
                  this.messages[platform] = '⚠️ ' + errMsg;
                  this.messageType[platform] = 'error';
                } else if (verifyRes?.status === 'success') {
                  this.messages[platform] = '✅ ' + verifyRes.message + ' — Future posts will publish automatically!';
                  this.messageType[platform] = 'success';
                  this.expanded[platform] = false; // auto-collapse on success
                } else {
                  this.messages[platform] = verifyRes?.message || 'Connection failed.';
                  this.messageType[platform] = 'error';
                }
                this.loadCredentials();
                this.cdr.detectChanges();
              }
            });
        }
      });
  }

  /** Kept for internal use — called by saveAndConnect */
  verifyCredentials(platform: string): void {
    this.actionLoading[platform] = true;
    this.messages[platform] = '';
    const headers = new HttpHeaders({ Authorization: 'Bearer ' + localStorage.getItem('auth_token') });
    this.http.post<any>(`/api/credentials/${platform}/verify`, {}, { headers })
      .pipe(timeout(15000), catchError(err => of({ error: true, _err: err })))
      .subscribe({
        next: (res) => {
          this.actionLoading[platform] = false;
          this.messages[platform] = res?.error ? (res._err?.error?.message || 'Verification failed.') : res.message;
          this.messageType[platform] = (!res?.error && res?.status === 'success') ? 'success' : 'error';
          this.loadCredentials();
          this.cdr.detectChanges();
        }
      });
  }

  disconnectPlatform(platform: string): void {
    if (!confirm(`Remove ${platform} credentials? This will stop any future posts to this platform.`)) return;

    this.actionLoading[platform] = true;
    const headers = new HttpHeaders({ Authorization: 'Bearer ' + localStorage.getItem('auth_token') });

    this.http.delete<any>(`/api/credentials/${platform}`, { headers })
      .pipe(timeout(10000), catchError(err => of({ error: true })))
      .subscribe({
        next: () => {
          this.actionLoading[platform] = false;
          this.expanded[platform] = false;
          this.loadCredentials();
          this.cdr.detectChanges();
        }
      });
  }

  goBack(): void {
    this.router.navigate(['/dashboard']);
  }
}
