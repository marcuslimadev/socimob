# Architecture and Design Document — Google Ads Integration

## 1. Identification

- System: Socimob
- Module: Google Ads
- Google Ads Account: 529-605-5627
- Objective: allow real estate administrators to select active property listings, create Google Ads campaigns, define budget per listing, set campaign validity dates, and monitor performance metrics by campaign.

## 2. System Architecture

The integration connects Socimob, the application database, background processing queues, and the Google Ads API.

### Components

1. Google Ads Screen
   - Lists active property listings for the real estate company.
   - Allows multiple property selection.
   - Allows daily budget definition per property.
   - Allows campaign start date and end date definition.
   - Displays publishing status, cost, clicks, impressions, leads, and conversions.

2. Socimob Backend
   - Receives requests from the screen.
   - Validates permissions, subscribed plan, active listings, and required property fields.
   - Creates internal records for connections, campaigns, published listings, leads, and audit logs.
   - Dispatches asynchronous jobs to communicate with Google Ads.

3. Google Ads Adapter
   - Encapsulates provider-specific calls to the Google Ads API.
   - Uses OAuth 2.0, refresh token, customer ID, and developer token.
   - Creates campaigns, budgets, ads, and retrieves reports.

4. Processing Queues
   - Process campaign creation and updates outside the web request.
   - Prevent the user interface from blocking while external API calls are running.
   - Allow controlled retries and failure tracking.

5. Database
   - Stores encrypted tokens, ad accounts, campaigns, listing links, captured leads, summarized metrics, and audit logs.

## 3. Google Ads Credentials and Access

For production, the integration requires the following information:

- Google Ads Customer ID: 529-605-5627
- Developer Token with Basic Access or higher approved by Google.
- OAuth Client ID.
- OAuth Client Secret.
- OAuth Refresh Token generated with the scope https://www.googleapis.com/auth/adwords.
- Login Customer ID, if the account is managed through an MCC account.

Tokens and secrets will not be displayed in the user interface or stored in plain text. The system must store access tokens and refresh tokens encrypted.

## 4. Screen Mockups

### 4.1 Main Screen — Google Ads

```text
+---------------------------------------------------------------+
| Google Ads                                                    |
| Connection: Google Ads connected | Account: 529-605-5627      |
+---------------------------------------------------------------+
| [Search listing] [Purpose] [City] [Status: Active]            |
+---------------------------------------------------------------+
| [ ] Code | Property               | City   | Price | Status   |
| [x] 1023 | 2-bed apartment Center | Itajai | 450k  | Active   |
| [x] 1048 | House with pool        | Camb.  | 890k  | Active   |
| [ ] 1081 | Commercial office      | BC     | 320k  | Active   |
+---------------------------------------------------------------+
| Daily budget per property: BRL [ 50.00 ]                      |
| Start date: [__/__/____]  End date: [__/__/____]              |
| Target region: [City/Neighborhood/Radius]                     |
| [Generate Google Ads campaign]                                |
+---------------------------------------------------------------+
```

### 4.2 Campaign Confirmation

```text
+---------------------------------------------------------------+
| Confirm campaign creation                                     |
+---------------------------------------------------------------+
| Selected properties: 2                                        |
| Estimated total daily budget: BRL 100.00                      |
| Period: 2026-07-01 to 2026-07-31                              |
| Objective: lead generation                                    |
| Click destination: public property page                       |
+---------------------------------------------------------------+
| [Cancel] [Create campaigns]                                   |
+---------------------------------------------------------------+
```

### 4.3 Campaign Analytics

```text
+---------------------------------------------------------------+
| Analytics — Google Ads Campaign                               |
+---------------------------------------------------------------+
| Campaign: 2-bed apartment Center                              |
| Status: Active | Period: 2026-07-01 - 2026-07-31              |
+---------------------------------------------------------------+
| Impressions | Clicks | CTR | Cost | Avg. CPC | Leads          |
| 12,430      | 386    |3.1% |BRL420|BRL1.09   | 18             |
+---------------------------------------------------------------+
| Daily performance chart                                       |
| [line: clicks] [line: cost] [line: leads]                     |
+---------------------------------------------------------------+
| Received leads                                                |
| Name | Phone | Property | Source | Date                       |
+---------------------------------------------------------------+
```

## 5. Data Flow — Campaign Creation

1. The administrator opens the Google Ads screen.
2. The frontend retrieves the tenant's active property listings.
3. The administrator selects one or more listings.
4. The administrator provides daily budget, start date, end date, and target region.
5. The backend validates:
   - authenticated user;
   - correct tenant;
   - active Google Ads connection;
   - plan permission;
   - active property listing;
   - photos, title, description, price, and public property URL.
6. The backend creates or updates internal records in ads_listings and ads_campaigns.
7. The backend writes an audit entry in ads_audit_logs.
8. The backend dispatches jobs to create or update resources through the Google Ads API.
9. The Google Ads Adapter creates the budget, campaign, ad group, assets, and ads.
10. External IDs returned by the Google Ads API are stored in the database.
11. The screen displays the campaign status as “publishing”, “active”, “paused”, or “error”.

## 6. Data Flow — Analytics

1. A scheduled job periodically retrieves reports from the Google Ads API.
2. Metrics are associated with the tenant, campaign, and property listing.
3. The system displays impressions, clicks, CTR, cost, average CPC, conversions, and leads.
4. If a lead form or conversion tracking is configured, the system imports leads and normalizes name, phone, email, message, and property of interest.
5. Duplicate leads are identified before creating CRM records.
6. Valid leads are linked to Person, CRM Lead, and property listing records.

## 7. Data Storage

Tables planned or already present in the ads module:

- ads_connections: OAuth connection by tenant and provider.
- ads_accounts: Google Ads account linked to the tenant.
- ads_listings: link between Socimob property listing and external item/ad.
- ads_campaigns: campaigns, budget, status, region, and external IDs.
- ads_leads: leads captured by Google Ads before full CRM normalization.
- ads_audit_logs: sanitized audit trail for operations.

Sensitive data:

- access_token and refresh_token must be encrypted.
- client secret and developer token must be stored in environment variables or a secrets vault.
- logs must never store tokens, secrets, or sensitive payloads without sanitization.

## 8. Security and Permissions

- Only authorized real estate administrators can connect a Google Ads account and create campaigns.
- Brokers can view analytics only for campaigns/properties allowed by their role.
- All operations must enforce tenant_id isolation.
- External operations must be audited with request_id.
- API failures must be logged in a sanitized format.

## 9. Error Handling

- No Google Ads connection: block campaign creation and instruct the user to reconnect.
- Expired token: attempt automatic token refresh.
- Developer token without production access: block real campaigns and indicate that Basic Access approval is required.
- Incomplete property listing: show missing fields before creating the campaign.
- Google Ads API error: store ERROR status and sanitized message.

## 10. First Release Scope

The first version of the Google Ads screen should deliver:

- Active property listing.
- Multiple property selection.
- Daily budget per property.
- Campaign start date and end date.
- Asynchronous campaign creation.
- Status by property/campaign.
- Basic analytics: impressions, clicks, cost, CTR, average CPC, and leads.

## 11. Out of Initial Scope

- Automatic bid optimization using AI.
- Advanced remarketing.
- Customer Match.
- Automatic A/B testing.
- Automatic budget redistribution to better-performing properties.
