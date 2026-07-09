import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { PostService } from '../post.service';
import { HttpClient, HttpHeaders } from '@angular/common/http';

@Component({
  selector: 'app-create-post',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink],
  templateUrl: './create-post.html',
  styleUrl: './create-post.css'
})
export class CreatePost implements OnInit {
  postForm: FormGroup;
  isLoading = false;
  successMessage = '';
  errorMessage = '';
  isTwitterConnected = false;
  checkedConnection = false;

  // Platforms supported
  platforms = [
    { key: 'X/Twitter', label: '𝕏  X/Twitter', limit: 280 },
    { key: 'Bluesky',   label: '🦋 Bluesky',   limit: 300 },
    { key: 'Reddit',    label: '🤖 Reddit',     limit: 40000 },
  ];

  selectedPlatforms: string[] = ['X/Twitter'];

  // Media files tracking
  attachedFiles: File[] = [];
  mediaPreviews: string[] = [];

  constructor(
    private fb: FormBuilder,
    private postService: PostService,
    private router: Router,
    private http: HttpClient,
    private cdr: ChangeDetectorRef
  ) {
    this.postForm = this.fb.group({
      title: ['', [Validators.required, Validators.minLength(3), Validators.maxLength(100)]],
      content: ['', [Validators.required, Validators.minLength(5)]],
      scheduled_date: ['', Validators.required],
      scheduled_time: ['', Validators.required],
      timezone: [typeof Intl !== 'undefined' ? Intl.DateTimeFormat().resolvedOptions().timeZone : 'UTC', Validators.required],
      recurrence: ['once', Validators.required]
    });
  }

  ngOnInit() {
    this.checkTwitterConnection();

    // Set default schedule to current date/time + 2 hours
    const defaultDate = new Date();
    defaultDate.setHours(defaultDate.getHours() + 2);
    
    const year = defaultDate.getFullYear();
    const month = String(defaultDate.getMonth() + 1).padStart(2, '0');
    const day = String(defaultDate.getDate()).padStart(2, '0');
    const hours = String(defaultDate.getHours()).padStart(2, '0');
    const minutes = String(defaultDate.getMinutes()).padStart(2, '0');

    this.postForm.patchValue({
      scheduled_date: `${year}-${month}-${day}`,
      scheduled_time: `${hours}:${minutes}`
    });

    // Check for duplicate prefill data
    if (typeof window !== 'undefined') {
      const dupData = localStorage.getItem('duplicate_post_data');
      if (dupData) {
        try {
          const parsed = JSON.parse(dupData);
          this.postForm.patchValue({
            title: parsed.title,
            content: parsed.content
          });
          this.selectedPlatforms = parsed.platforms || ['X/Twitter'];
          localStorage.removeItem('duplicate_post_data');
        } catch (e) {
          console.error('Failed to parse duplicated post data:', e);
        }
      }
    }
  }

  checkTwitterConnection() {
    if (typeof window === 'undefined') return;
    const token = localStorage.getItem('auth_token');
    if (!token) return;

    const headers = new HttpHeaders({ Authorization: 'Bearer ' + token });
    this.http.get<any>('/api/credentials', { headers }).subscribe({
      next: (res) => {
        this.checkedConnection = true;
        this.isTwitterConnected = res.credentials?.twitter?.is_verified ?? false;
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Failed to load connection status', err);
        this.checkedConnection = true;
        this.isTwitterConnected = false;
        this.cdr.detectChanges();
      }
    });
  }

  get charCount(): number {
    return (this.postForm.get('content')?.value || '').length;
  }

  get charLimit(): number {
    // Return the minimum character limit among selected platforms
    let minLimit = 5000;
    this.selectedPlatforms.forEach(pName => {
      const plat = this.platforms.find(pl => pl.key === pName);
      if (plat && plat.limit < minLimit) {
        minLimit = plat.limit;
      }
    });
    return minLimit;
  }

  get charLimitClass(): string {
    const count = this.charCount;
    const limit = this.charLimit;
    if (count > limit) return 'char-danger';
    if (count > limit - 20) return 'char-warn';
    return 'char-ok';
  }

  isInvalid(field: string): boolean {
    const ctrl = this.postForm.get(field);
    return !!(ctrl && ctrl.invalid && ctrl.touched);
  }

  togglePlatform(platformKey: string) {
    const idx = this.selectedPlatforms.indexOf(platformKey);
    if (idx > -1) {
      if (this.selectedPlatforms.length > 1) {
        this.selectedPlatforms.splice(idx, 1);
      }
    } else {
      this.selectedPlatforms.push(platformKey);
    }
  }

  isPlatformSelected(platformKey: string): boolean {
    return this.selectedPlatforms.includes(platformKey);
  }

  // Media Handlers
  onFileSelected(event: any) {
    const files: FileList = event.target.files;
    if (!files) return;

    for (let i = 0; i < files.length; i++) {
      const file = files[i];
      
      // Limit file count
      if (this.attachedFiles.length >= 4) {
        this.errorMessage = 'You can upload up to 4 media attachments.';
        return;
      }

      this.attachedFiles.push(file);

      // Generate preview
      const reader = new FileReader();
      reader.onload = (e: any) => {
        this.mediaPreviews.push(e.target.result);
      };
      reader.readAsDataURL(file);
    }
  }

  removeMedia(index: number) {
    this.attachedFiles.splice(index, 1);
    this.mediaPreviews.splice(index, 1);
  }

  onSubmit() {
    // Validate character limit
    if (this.charCount > this.charLimit) {
      this.errorMessage = `Content exceeds the character limit of ${this.charLimit} characters for selected platforms.`;
      return;
    }

    if (this.postForm.invalid) {
      this.postForm.markAllAsTouched();
      return;
    }

    this.isLoading = true;
    this.successMessage = '';
    this.errorMessage = '';

    const formVal = this.postForm.value;
    const localDateTime = new Date(`${formVal.scheduled_date}T${formVal.scheduled_time}`);
    let scheduled_at = '';
    
    if (!isNaN(localDateTime.getTime())) {
      scheduled_at = localDateTime.toISOString();
    } else {
      scheduled_at = `${formVal.scheduled_date} ${formVal.scheduled_time}:00`;
    }

    // Construct FormData for multipart transmission
    const formData = new FormData();
    formData.append('title', formVal.title);
    formData.append('content', formVal.content);
    formData.append('scheduled_at', scheduled_at);
    formData.append('timezone', formVal.timezone);
    formData.append('recurrence', formVal.recurrence);
    
    // Append platforms list
    this.selectedPlatforms.forEach((p, idx) => {
      formData.append(`platforms[${idx}]`, p);
    });

    // Append attachments
    this.attachedFiles.forEach(file => {
      formData.append('files[]', file);
    });

    this.postService.createPost(formData).subscribe({
      next: () => {
        this.isLoading = false;
        this.successMessage = '✅ Post scheduled successfully!';
        this.postForm.reset({
          timezone: 'UTC',
          recurrence: 'once'
        });
        this.attachedFiles = [];
        this.mediaPreviews = [];
        this.selectedPlatforms = ['X/Twitter'];
        this.cdr.detectChanges();
        setTimeout(() => this.router.navigate(['/scheduled-posts']), 1800);
      },
      error: (error) => {
        this.isLoading = false;
        console.error('Create post error:', error);
        if (error.error?.message) {
          this.errorMessage = error.error.message;
        } else if (error.error?.errors) {
          const key = Object.keys(error.error.errors)[0];
          this.errorMessage = error.error.errors[key][0];
        } else {
          this.errorMessage = '❌ Failed to schedule post. Ensure Laravel API is running.';
        }
        this.cdr.detectChanges();
      }
    });
  }
}
