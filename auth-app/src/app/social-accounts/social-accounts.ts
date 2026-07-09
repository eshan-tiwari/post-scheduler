import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterModule } from '@angular/router';
import { PostService, ConnectedAccount } from '../post.service';

@Component({
  selector: 'app-social-accounts',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './social-accounts.html',
  styleUrl: './social-accounts.css'
})
export class SocialAccounts implements OnInit {
  connectedAccounts: ConnectedAccount[] = [];
  isLoading = true;
  successMessage = '';
  errorMessage = '';

  // Configured platforms in system
  platformsList: { key: string; label: string; icon: string; color: string; desc: string; usesApiKey?: boolean }[] = [
    { key: 'twitter',   label: 'X / Twitter',          icon: '𝕏',   color: '#1da1f2', desc: 'Post tweets, images, and threads.' },
    { key: 'bluesky',   label: 'Bluesky',               icon: '🦋',  color: '#0085ff', desc: 'Post to Bluesky using your handle and app password.', usesApiKey: true },
    { key: 'instagram', label: 'Instagram Business',    icon: '📸',  color: '#e1306c', desc: 'Publish business photos & videos.' },
    { key: 'facebook',  label: 'Facebook Pages',        icon: '👥',  color: '#1877f2', desc: 'Manage & publish to your pages.' },
    { key: 'linkedin',  label: 'LinkedIn Profile',      icon: '💼',  color: '#0a66c2', desc: 'Share articles and business updates.' }
  ];

  constructor(private postService: PostService, private cdr: ChangeDetectorRef, private router: Router) {}

  ngOnInit() {
    this.loadAccounts();
  }

  loadAccounts() {
    this.isLoading = true;
    this.postService.getSocialAccounts().subscribe({
      next: (res) => {
        this.connectedAccounts = res.accounts || [];
        this.isLoading = false;
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error loading social accounts:', err);
        this.errorMessage = 'Failed to load social accounts. Ensure backend is running.';
        this.isLoading = false;
        this.cdr.detectChanges();
      }
    });
  }

  isAccountConnected(platform: string): boolean {
    return this.connectedAccounts.some(acc => acc.platform.toLowerCase() === platform.toLowerCase());
  }

  getAccountDetails(platform: string): ConnectedAccount | undefined {
    return this.connectedAccounts.find(acc => acc.platform.toLowerCase() === platform.toLowerCase());
  }

  getConnectedCount(): number {
    return this.platformsList.filter(p => this.isAccountConnected(p.key)).length;
  }

  /** Bluesky and other API-key platforms → go to credentials page */
  navigateToConnect() {
    this.router.navigate(['/connect-platforms']);
  }

  connectAccount(platform: string) {
    this.isLoading = true;
    this.errorMessage = '';
    this.postService.connectSocialAccount(platform).subscribe({
      next: (res) => {
        if (res.url) {
          // Redirect to authorization url (supports mock flow redirect)
          window.location.href = res.url;
        } else {
          this.errorMessage = 'Failed to generate oauth URL.';
          this.isLoading = false;
          this.cdr.detectChanges();
        }
      },
      error: (err) => {
        console.error('OAuth initiation error:', err);
        this.errorMessage = 'Failed to initiate connection: ' .concat(err.error?.message || 'Server error');
        this.isLoading = false;
        this.cdr.detectChanges();
      }
    });
  }

  disconnectAccount(platform: string) {
    const acc = this.getAccountDetails(platform);
    if (!acc) return;

    if (!confirm(`Are you sure you want to disconnect your ${platform} integration?`)) return;

    this.isLoading = true;
    this.postService.disconnectSocialAccount(acc.id).subscribe({
      next: () => {
        this.successMessage = `Successfully disconnected ${acc.username} from ${platform}.`;
        this.loadAccounts();
        setTimeout(() => this.successMessage = '', 3500);
      },
      error: (err) => {
        console.error('Disconnect error:', err);
        this.errorMessage = 'Failed to disconnect account.';
        this.isLoading = false;
        this.cdr.detectChanges();
      }
    });
  }
}
