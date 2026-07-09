import { Component, OnInit, ChangeDetectorRef, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink, Router } from '@angular/router';
import { PostService, ScheduledPost } from '../post.service';


@Component({
  selector: 'app-scheduled-posts',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './scheduled-posts.html',
  styleUrl: './scheduled-posts.css'
})
export class ScheduledPosts implements OnInit, OnDestroy {
  posts: ScheduledPost[] = [];
  isLoading = true;
  successMessage = '';
  errorMessage = '';

  // Filter & Search states
  selectedStatus = 'All';
  searchQuery = '';
  currentPage = 1;
  lastPage = 1;
  totalPostsCount = 0;

  // Countdown timer interval
  private countdownInterval: any;
  // Background status polling interval (5 seconds)
  private pollInterval: any;
  // Flag to track if a background poll is in progress
  private silentLoading = false;


  constructor(
    private postService: PostService,
    private router: Router,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit() {
    this.loadPosts();

    // Refresh countdown labels every 30 seconds
    this.countdownInterval = setInterval(() => {
      this.cdr.detectChanges();
    }, 30000);

    // Poll for live status updates every 5 seconds (silent — no loading spinner)
    this.pollInterval = setInterval(() => {
      if (!this.silentLoading) {
        this.silentRefresh();
      }
    }, 5000);
  }


  ngOnDestroy() {
    if (this.countdownInterval) clearInterval(this.countdownInterval);
    if (this.pollInterval)     clearInterval(this.pollInterval);
  }


  loadPosts(page = 1) {
    this.isLoading = true;
    this.errorMessage = '';
    this.currentPage = page;

    const params = {
      status: this.selectedStatus,
      search: this.searchQuery,
      page: this.currentPage
    };

    this.postService.getPosts(params).subscribe({
      next: (res) => {
        this.posts = res.data || [];
        this.currentPage = res.current_page || 1;
        this.lastPage = res.last_page || 1;
        this.totalPostsCount = res.total || 0;
        this.isLoading = false;
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error loading posts:', err);
        this.errorMessage = 'Failed to load posts from server.';
        this.isLoading = false;
        this.cdr.detectChanges();
      }
    });
  }

  /** Silent background refresh — updates statuses without showing the loading spinner */
  private silentRefresh() {
    this.silentLoading = true;
    const params = {
      status: this.selectedStatus,
      search: this.searchQuery,
      page: this.currentPage
    };

    this.postService.getPosts(params).subscribe({
      next: (res) => {
        const fresh = res.data || [];
        // Only update if any status has actually changed — avoids flicker
        let changed = false;
        fresh.forEach((newPost: ScheduledPost) => {
          const existing = this.posts.find(p => p.id === newPost.id);
          if (existing && existing.status !== newPost.status) {
            existing.status = newPost.status;
            existing.error_message = newPost.error_message;
            changed = true;
          }
        });
        // Also update pagination counts
        this.totalPostsCount = res.total || this.totalPostsCount;
        if (changed) {
          this.cdr.detectChanges();
        }
        this.silentLoading = false;
      },
      error: () => {
        // Silently ignore poll errors — next poll will retry
        this.silentLoading = false;
      }
    });
  }


  onStatusFilterChange(status: string) {
    this.selectedStatus = status;
    this.loadPosts(1);
  }

  onSearch(event: any) {
    this.searchQuery = event.target.value;
    this.loadPosts(1);
  }

  // Action: Toggle Pause/Resume status of a post
  togglePause(post: ScheduledPost) {
    if (!post.id) return;
    const newStatus = post.status === 'Paused' ? 'Pending' : 'Paused';
    
    this.postService.updatePost(post.id, { status: newStatus }).subscribe({
      next: () => {
        this.successMessage = `✅ Post is now ${newStatus === 'Pending' ? 'active (Pending)' : 'paused'}.`;
        this.loadPosts(this.currentPage);
        setTimeout(() => this.successMessage = '', 3500);
      },
      error: (err) => {
        console.error('Toggle status error:', err);
        this.errorMessage = 'Failed to update post status.';
      }
    });
  }

  // Action: Retry failed post publication immediately
  retryPublish(post: ScheduledPost) {
    if (!post.id) return;
    
    this.isLoading = true;
    this.postService.retryPost(post.id).subscribe({
      next: (res) => {
        this.successMessage = `🚀 Retry dispatched! ${res.message || ''}`;
        setTimeout(() => this.successMessage = '', 4500);
        this.loadPosts(this.currentPage);
      },
      error: (err) => {
        console.error('Retry error:', err);
        this.errorMessage = err.error?.message || 'Failed to retry publishing post.';
        this.isLoading = false;
        this.cdr.detectChanges();
      }
    });
  }

  // Action: Duplicate post data and open creator
  duplicatePost(post: ScheduledPost) {
    // Store data in local storage and navigate to create post (which can pre-fill)
    const duplicateData = {
      title: `${post.title} (Copy)`,
      content: post.content,
      platforms: post.platforms || [post.platform]
    };
    
    if (typeof window !== 'undefined') {
      localStorage.setItem('duplicate_post_data', JSON.stringify(duplicateData));
    }
    
    this.router.navigate(['/create-post']);
  }

  deletePost(post: ScheduledPost) {
    if (!post.id) return;
    if (!confirm(`Are you sure you want to delete "${post.title}"?`)) return;

    this.postService.deletePost(post.id).subscribe({
      next: () => {
        this.successMessage = `🗑️ "${post.title}" has been deleted.`;
        this.loadPosts(this.currentPage);
        setTimeout(() => (this.successMessage = ''), 3500);
      },
      error: () => {
        this.errorMessage = '❌ Failed to delete post.';
        setTimeout(() => (this.errorMessage = ''), 3500);
      }
    });
  }

  // Time calculations
  formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleString('en-IN', {
      dateStyle: 'medium',
      timeStyle: 'short'
    });
  }

  isOverdue(post: ScheduledPost): boolean {
    return post.status === 'Pending' && new Date(post.scheduled_at) <= new Date();
  }

  // Calculate live countdown to publishing time
  getCountdown(dateStr: string): string {
    const target = new Date(dateStr).getTime();
    const now = new Date().getTime();
    const diff = target - now;

    if (diff <= 0) {
      return 'Due now / processing';
    }

    const mins = Math.floor(diff / 60000);
    const hours = Math.floor(mins / 60);
    const days = Math.floor(hours / 24);

    if (days > 0) {
      return `${days}d ${hours % 24}h remaining`;
    }
    if (hours > 0) {
      return `${hours}h ${mins % 60}m remaining`;
    }
    return `${mins}m remaining`;
  }
}
