# IndexedDB Security Enhancement Plan

Here's a comprehensive checklist to implement the security recommendations for your IndexedDB implementation:

## 1. Add Data Validation ✅

- [x] Create validation functions for each store type
- [x] Implement schema validation for each data type
- [x] Add validation checks before saving data
- [x] Create error handling for validation failures

```javascript:db.js
// Data validation schemas
const SCHEMAS = {
    [STORES.CHATS]: {
        required: ['chatId', 'chats', 'contacts'],
        types: {
            chatId: 'string',
            chats: 'object',
            contacts: 'object',
            version: 'number',
            lastUpdated: 'number'
        }
    },
    [STORES.CONTACTS]: {
        required: ['address', 'contacts'],
        types: {
            address: 'string',
            contacts: 'object',
            version: 'number',
            lastUpdated: 'number'
        }
    },
    [STORES.WALLET]: {
        required: ['assetId', 'wallet'],
        types: {
            assetId: 'string',
            wallet: 'object',
            version: 'number',
            lastUpdated: 'number'
        }
    }
};

// Custom error classes
class DBError extends Error {
    constructor(message) {
        super(message);
        this.name = 'DBError';
    }
}

class ValidationError extends DBError {
    constructor(message) {
        super(message);
        this.name = 'ValidationError';
    }
}

// Validate data against schema
function validateData(storeName, data) {
    const schema = SCHEMAS[storeName];
    if (!schema) throw new ValidationError(`No schema defined for store: ${storeName}`);

    // Check required fields
    for (const field of schema.required) {
        if (data[field] === undefined) {
            throw new ValidationError(`Missing required field: ${field}`);
        }
    }

    // Check types
    for (const [field, type] of Object.entries(schema.types)) {
        if (data[field] !== undefined && typeof data[field] !== type) {
            throw new ValidationError(`Field ${field} should be type ${type}`);
        }
    }

    return true;
}

// Update saveData to use validation
async function saveData(storeName, data) {
    try {
        validateData(storeName, data);
        const db = await initDB();
        // ... rest of the code ...
    } catch (error) {
        console.error(`Database error in saveData: ${error.message}`);
        throw error;
    }
}
```

## 2. Implement Data Encryption

- [ ] Add encryption utilities
- [ ] Create encrypted versions of save/get functions
- [ ] Implement secure key management
- [ ] Add option to encrypt specific fields only

```javascript:db.js
// ... existing code ...

// Simple encryption utilities (replace with a proper encryption library)
async function encryptData(data, encryptionKey) {
  // This is a placeholder - use a proper encryption library like CryptoJS or the Web Crypto API
  if (!encryptionKey) throw new Error('Encryption key is required');

  // Convert data to string if it's an object
  const dataStr = typeof data === 'object' ? JSON.stringify(data) : String(data);

  // In a real implementation, use proper encryption
  // Example with Web Crypto API (simplified):
  // const encoder = new TextEncoder();
  // const dataBuffer = encoder.encode(dataStr);
  // const encryptedBuffer = await window.crypto.subtle.encrypt(
  //   { name: 'AES-GCM', iv: window.crypto.getRandomValues(new Uint8Array(12)) },
  //   await deriveKey(encryptionKey),
  //   dataBuffer
  // );

  // For this example, just return a marked version
  return { encrypted: true, data: dataStr, timestamp: Date.now() };
}

async function decryptData(encryptedData, encryptionKey) {
  // Placeholder for actual decryption
  if (!encryptedData.encrypted) return encryptedData;

  // In a real implementation, use proper decryption
  return JSON.parse(encryptedData.data);
}

// Encrypted data operations
async function saveEncryptedData(storeName, data, encryptionKey) {
  const encryptedData = await encryptData(data, encryptionKey);
  return saveData(storeName, {
    ...data,
    _encrypted: true,
    _encryptedData: encryptedData
  });
}

async function getEncryptedData(storeName, key, encryptionKey) {
  const data = await getData(storeName, key);
  if (!data || !data._encrypted) return data;

  const decryptedData = await decryptData(data._encryptedData, encryptionKey);
  return { ...data, ...decryptedData, _encrypted: undefined, _encryptedData: undefined };
}
// ... existing code ...
```

## 3. Improve Error Handling ✅

- [x] Add specific error types
- [x] Implement graceful error handling for all operations
- [x] Add logging for database errors
- [x] Create recovery mechanisms

```javascript:db.js
// Custom error classes with enhanced features
class DBError extends Error {
    constructor(message) {
        super(message);
        this.name = 'DBError';
        this.timestamp = Date.now();
    }

    toJSON() {
        return {
            name: this.name,
            message: this.message,
            timestamp: this.timestamp,
            stack: this.stack
        };
    }
}

class TransactionError extends DBError {
    constructor(message, storeName, operation) {
        super(message);
        this.name = 'TransactionError';
        this.storeName = storeName;
        this.operation = operation;
    }
}

// Error logging system
const errorLog = [];
const MAX_ERROR_LOG_SIZE = 100;

function logError(error, context = {}) {
    const errorEntry = {
        error: error.toJSON ? error.toJSON() : {
            name: error.name,
            message: error.message,
            stack: error.stack
        },
        context,
        timestamp: Date.now()
    };

    console.error('Database Error:', errorEntry);
    errorLog.unshift(errorEntry);

    if (errorLog.length > MAX_ERROR_LOG_SIZE) {
        errorLog.pop();
    }
}

// Recovery mechanism with retry logic
async function retryOperation(operation, maxRetries = 3, delay = 1000) {
    let lastError;

    for (let i = 0; i < maxRetries; i++) {
        try {
            return await operation();
        } catch (error) {
            lastError = error;
            logError(error, { attempt: i + 1, maxRetries });

            if (i < maxRetries - 1) {
                await new Promise(resolve => setTimeout(resolve, delay * (i + 1)));
            }
        }
    }

    throw lastError;
}

// Enhanced database operations
async function saveData(storeName, data) {
    return retryOperation(async () => {
        try {
            validateData(storeName, data);
            const db = await initDB();
            // ... transaction code ...
        } catch (error) {
            logError(error, { storeName, data });
            throw error;
        }
    });
}
```

## 4. Implement Connection Management ✅

- [x] Create a connection pool
- [x] Add connection timeout handling
- [x] Implement proper connection closing
- [x] Add connection status monitoring

```javascript:db.js
// Connection management configuration
const CONNECTION_CONFIG = {
    POOL_SIZE: 3,
    IDLE_TIMEOUT: 60000, // 1 minute
    MAX_RETRY_ATTEMPTS: 3,
    RETRY_DELAY: 1000
};

// Connection pool management
class ConnectionPool {
    constructor() {
        this.connections = new Map(); // Map of connection ID to connection info
        this.lastConnectionId = 0;
    }

    async acquire() {
        // Clean up expired connections first
        this.cleanupExpiredConnections();

        // Check for available connection
        for (const [id, connInfo] of this.connections) {
            if (!connInfo.inUse) {
                connInfo.inUse = true;
                connInfo.lastUsed = Date.now();
                return { id, connection: connInfo.connection };
            }
        }

        // Create new connection if pool not full
        if (this.connections.size < CONNECTION_CONFIG.POOL_SIZE) {
            const id = ++this.lastConnectionId;
            const connection = await this.createConnection();
            this.connections.set(id, {
                connection,
                inUse: true,
                lastUsed: Date.now(),
                created: Date.now()
            });
            return { id, connection };
        }

        throw new ConnectionError('Connection pool exhausted');
    }

    // Connection monitoring
    getStats() {
        const now = Date.now();
        return {
            totalConnections: this.connections.size,
            activeConnections: Array.from(this.connections.values()).filter(c => c.inUse).length,
            idleConnections: Array.from(this.connections.values()).filter(c => !c.inUse).length,
            oldestConnection: Math.min(...Array.from(this.connections.values()).map(c => c.created)),
            connectionAges: Array.from(this.connections.entries()).map(([id, c]) => ({
                id,
                age: now - c.created,
                idle: now - c.lastUsed,
                inUse: c.inUse
            }))
        };
    }
}

// Connection management wrapper
async function withConnection(operation) {
    let connInfo = null;
    try {
        connInfo = await connectionPool.acquire();
        const result = await operation(connInfo.connection);
        return result;
    } finally {
        if (connInfo) {
            connectionPool.release(connInfo.id);
        }
    }
}

// Enhanced database operations using connection pool
async function saveData(storeName, data) {
    return retryOperation(async () => {
        try {
            validateData(storeName, data);
            return await withConnection(async (db) => {
                // ... transaction code ...
            });
        } catch (error) {
            logError(error, { storeName, data });
            throw error;
        }
    });
}
```

## 5. Add Security Auditing

- [ ] Implement access logging
- [ ] Add data change tracking
- [ ] Create audit reports
- [ ] Set up periodic security reviews

```javascript:db.js
// ... existing code ...

// Simple audit logging
function logDatabaseAccess(operation, storeName, key = null) {
  const logEntry = {
    timestamp: Date.now(),
    operation,
    storeName,
    key,
    user: getCurrentUser() // You would need to implement this
  };

  console.log('DB Access:', logEntry);

  // In a real application, you might:
  // 1. Store logs in a separate IndexedDB store
  // 2. Periodically sync logs to server
  // 3. Implement log rotation
}

// Update functions to include logging
async function saveData(storeName, data) {
  try {
    // ... existing validation ...
    logDatabaseAccess('save', storeName, data.id || data.chatId || data.address || data.assetId);
    const db = await getConnection();
    // ... existing code ...
  } catch (error) {
    // ... error handling ...
  }
}
// ... existing code ...
```

## 6. Add Data Sanitization

- [ ] Implement input sanitization
- [ ] Add output encoding
- [ ] Create content security policies
- [ ] Prevent script injection in stored data

```javascript:db.js
// ... existing code ...

// Basic sanitization function
function sanitizeData(data) {
  if (typeof data !== 'object' || data === null) return data;

  const sanitized = {};

  for (const [key, value] of Object.entries(data)) {
    // Recursively sanitize nested objects
    if (typeof value === 'object' && value !== null) {
      sanitized[key] = sanitizeData(value);
    }
    // Sanitize strings to prevent XSS
    else if (typeof value === 'string') {
      // Basic sanitization - in a real app, use a library like DOMPurify
      sanitized[key] = value
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#x27;')
        .replace(/\//g, '&#x2F;');
    }
    // Keep other types as is
    else {
      sanitized[key] = value;
    }
  }

  return sanitized;
}

// Update saveData to sanitize input
async function saveData(storeName, data) {
  try {
    validateData(storeName, data);
    const sanitizedData = sanitizeData(data);
    logDatabaseAccess('save', storeName, data.id || data.chatId || data.address || data.assetId);
    const db = await getConnection();
    // ... existing code using sanitizedData instead of data ...
  } catch (error) {
    // ... error handling ...
  }
}
// ... existing code ...
```

## Implementation Timeline

1. **Week 1**: Implement data validation and error handling
2. **Week 2**: Add connection management and sanitization
3. **Week 3**: Implement encryption for sensitive data
4. **Week 4**: Add audit logging and security monitoring

This plan provides a structured approach to enhancing the security of your IndexedDB implementation while maintaining functionality and performance.
