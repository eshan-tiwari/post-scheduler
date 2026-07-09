import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink, ActivatedRoute } from '@angular/router';
import { PostService, ScheduledPost } from '../post.service';

interface ActivityMonth {
  month: string;
  published: number;
  failed: number;
}

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './dashboard.html',
  styleUrl: './dashboard.css'
})
export class Dashboard implements OnInit {
  posts: ScheduledPost[] = [];
  isLoading = true;
  errorMessage = '';
  successNotification = '';

  // Dashboard metrics from API
  connectedAccounts = 0;
  successRate = 100.0;
  totalPosts = 0;
  pendingPostsCount = 0;
  publishedPostsCount = 0;
  failedPostsCount = 0;
  
  // Graph data
  activityGraph: ActivityMonth[] = [];
  
  // Recent activity logs
  recentLogs: any[] = [];

  constructor(
    private postService: PostService,
    private route: ActivatedRoute,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit() {
    // Check for query params indicating successful platform connections
    this.route.queryParams.subscribe(params => {
      if (params['connected']) {
        const platform = params['platform'] || 'Social';
        this.successNotification = `🔌 Successfully connected your ${platform} account!`;
        setTimeout(() => this.successNotification = '', 4500);
      } else if (params['error']) {
        this.errorMessage = `OAuth Error: ${decodeURIComponent(params['error'])}`;
        setTimeout(() => this.errorMessage = '', 6000);
      }
    });

    this.loadDashboardData();
  }

  loadDashboardData() {
    this.isLoading = true;
    this.errorMessage = '';

    // Load recent posts
    this.postService.getPosts({ per_page: 5 }).subscribe({
      next: (res) => {
        this.posts = res.data || [];
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error loading posts:', err);
      }
    });

    // Load dashboard stats
    this.postService.getDashboardStats().subscribe({
      next: (res) => {
        if (res.status === 'success') {
          const stats = res.data;
          this.connectedAccounts = stats.connected_accounts;
          this.successRate = stats.success_rate;
          this.totalPosts = stats.posts.total;
          this.pendingPostsCount = stats.posts.pending;
          this.publishedPostsCount = stats.posts.published;
          this.failedPostsCount = stats.posts.failed;
          this.activityGraph = stats.activity_graph || [];
        }
        this.isLoading = false;
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error loading dashboard stats:', err);
        this.errorMessage = 'Could not load statistics from backend.';
        this.isLoading = false;
        this.cdr.detectChanges();
      }
    });

    // Load publish logs
    this.postService.getPublishLogs().subscribe({
      next: (res) => {
        this.recentLogs = res.data || [];
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Error loading publish logs:', err);
      }
    });
  }

  // Calculate SVG attributes for visual chart rendering
  get maxBarValue(): number {
    let max = 5; // minimum scale top
    this.activityGraph.forEach(d => {
      const tot = d.published + d.failed;
      if (tot > max) max = tot;
    });
    return max;
  }

  getBarHeight(value: number, totalMax: number): number {
    if (totalMax === 0) return 0;
    return (value / totalMax) * 120; // 120px scale
  }

  formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleString('en-IN', {
      dateStyle: 'medium', timeStyle: 'short'
    });
  }
}
