# Risk Register

| Risk | Impact | Mitigation |
| ---- | ------ | ---------- |
| Payment gateway misconfiguration prevents checkout | High | Validate API keys via sandbox handshake, surface admin alerts, fallback to manual invoices |
| Webhook delivery failures | Medium | Idempotent transaction logging, retry queue via PaymentGatewayManager, capture manual reconciliation report |
| Inventory drift after payment | Medium | Inventory decrement triggers on `paid` state, audit log entry recorded for variances |
| GDPR requests not processed within SLA | Medium | GDPR export/anonymise API available to admins, add cron reminder hook |
| Packaging misses vendor assets | Medium | Documented packaging workflow with cache busting, include automated checklist |
