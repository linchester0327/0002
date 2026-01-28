# List Rendering Updates Based on Data Changes

This document outlines how specific functions modify data arrays (`myData.wallet.history`, `myData.contacts[addr].messages`, `myData.chats`) and how these changes trigger updates to the user interface lists.

## `handleSendAsset()`

**Function Goal:** Sends an asset (payment) from the user's wallet to a specified recipient username.

**Data Modifications:**

1.  **`myData.wallet.history`:**
    - Creates a `newPayment` object representing the _outgoing_ transaction.
    - Sets `newPayment.timestamp = Date.now()`.
    - **(Current Behavior):** Inserts this `newPayment` at the beginning of the `myData.wallet.history` array using `unshift()`.
    - **(Planned Behavior):** Will insert `newPayment` into `myData.wallet.history` in the correct chronological position using `insertSorted()`.

**UI Rendering Updates:**

1.  **Transaction History List:**
    - After successfully sending the transaction and adding it to `myData.wallet.history`, the function calls `openHistoryModal()`.
    - `openHistoryModal()` internally calls `updateTransactionHistory()`.
    - `updateTransactionHistory()` reads the potentially updated `myData.wallet.history` array and completely re-renders the HTML content of the `#transactionList` element, displaying the transactions in the order they appear in the array.

**Summary:** `handleSendAsset` directly modifies the transaction history data and then triggers a UI update (`openHistoryModal` -> `updateTransactionHistory`) that re-renders the history list based on the latest data. The planned refactoring will ensure the data added is chronologically sorted before the re-render occurs.

## `handleSendMessage()`

**Function Goal:** Sends a chat message from the user to the recipient specified in the currently open chat modal.

**Data Modifications:**

1.  **`myData.contacts[currentAddress].messages`:**
    - Creates a `newMessage` object representing the _outgoing_ chat message.
    - Sets `newMessage.timestamp = Date.now()`.
    - **(Current Behavior):** Appends this `newMessage` to the _end_ of the `messages` array for the current contact using `push()`.
    - **(Planned Behavior):** Will insert `newMessage` into the `messages` array in the correct chronological position using `insertSorted()`.
2.  **`myData.chats`:**
    - Creates a `chatUpdate` object containing the recipient's address and the `newMessage.timestamp`.
    - **(Current Behavior):** Removes the existing entry for this chat (if any) and inserts the `chatUpdate` at the _beginning_ of the `myData.chats` array using `unshift()`. This forces the current chat to the top of the main chat list.
    - **(Planned Behavior):** Will use the sorted insertion logic (like in `processChats`) to place the `chatUpdate` in the correct chronological position within `myData.chats`, maintaining consistent sorting.

**UI Rendering Updates:**

1.  **Chat Modal Message List (`#chatModal .messages-list`):**
    - `handleSendMessage` _itself_ doesn't directly trigger a re-render of the main `#chatList`.
    - After successfully sending the message and modifying the data arrays, the function calls `appendChatModal()`.
    - **(Current Behavior):** `appendChatModal()` iterates forward through any _new_ messages added to the `contact.messages` array (from index `len` to `end`) and appends their HTML representation to the _end_ of the message list currently displayed in the modal.
    - **(Planned Behavior - Hybrid Approach):** Since the underlying `contact.messages` array will be sorted descending, `appendChatModal` requires modification and state (`lastRenderedTimestamp`). When new messages arrive:
      - Identify all _new_ (unrendered) messages and find their minimum timestamp (`minNewTimestamp`).
      - **If `minNewTimestamp < lastRenderedTimestamp`** (or if it's the initial load): Trigger a **Full Re-render**. Clear the `messagesList`, iterate backwards through the _entire_ `contact.messages` array (oldest to newest), append each message's HTML. Update `lastRenderedTimestamp` with the timestamp of the last (newest) message rendered.
      - **Else:** Perform an **Optimized Append**. Iterate backwards through only the _new_ messages slice, append each message's HTML to the end of `messagesList` using `insertAdjacentHTML('beforeend', ...)`. Update `lastRenderedTimestamp` with the timestamp of the last (newest) message appended.
      - In both cases, scroll the message list to the bottom. This aims for performance but ensures correctness for out-of-order messages.
2.  **Main Chat List (`#chatList`):**
    - `handleSendMessage` _itself_ doesn't directly trigger a re-render of the main `#chatList`.
    - However, it modifies `myData.chats` (currently by `unshift`-ing, planned by `insertSorted`).
    - The next time `updateChatList()` is called (e.g., by switching screens, periodic polling, or receiving another message), it will read the modified `myData.chats` array and re-render the `#chatList` entirely, reflecting the new order and potentially updated timestamp/last message snippet for the affected chat.

**Summary:** `handleSendMessage` modifies the specific contact's message list and the main chat list order data. It directly triggers an update within the active chat modal (`appendChatModal`) and indirectly influences the next render of the main chat list view (`#chatList`) by altering the underlying `myData.chats` data.

## `processChats()`

**Function Goal:** Processes incoming messages and transfers fetched from the network for various senders.

**Data Modifications (per sender processed):**

1.  **`myData.contacts[from].messages` (for `tx.type == 'message'`):**
    - Processes the incoming message payload (`tx.xmessage`).
    - **(Current Behavior):** Sets `payload.timestamp = Date.now()`. Appends the processed `payload` to the _end_ of the `contact.messages` array using `push()`.
    - **(Planned Behavior):** Will set `payload.timestamp = payload.sent_timestamp`. Will insert the `payload` into the `contact.messages` array in the correct chronological position using `insertSorted()`.
    - Increments `contact.unread` counter.
2.  **`myData.wallet.history` (for `tx.type == 'transfer'`):**
    - Processes the incoming transfer payload (`tx.xmemo`).
    - Creates a `newPayment` object representing the _incoming_ transfer.
    - Sets `newPayment.timestamp = payload.sent_timestamp`.
    - **(Current Behavior):** Inserts `newPayment` at the beginning using `unshift()` and then immediately re-sorts the _entire_ `myData.wallet.history` array by timestamp.
    - **(Planned Behavior):** Will insert `newPayment` into `myData.wallet.history` in the correct chronological position using `insertSorted()`, eliminating the need for the separate sort step.
3.  **`myData.chats` (if new messages were added):**
    - Gets the latest message from the updated `contact.messages` array.
    - Creates a `chatUpdate` object with the sender's address and the `latestMessage.timestamp` (which will reflect `sent_timestamp` after planned changes).
    - Removes the existing entry for this chat (if any).
    - **(Current/Planned Behavior):** Inserts the `chatUpdate` object into the `myData.chats` array in the correct chronological position using sorted insertion logic (finds index based on timestamp and splices in). This part is already implemented correctly.

**UI Rendering Updates:**

1.  **Chat Modal Message List (`#chatModal .messages-list`):**
    - `processChats` _itself_ doesn't directly update the chat modal list.
    - However, if the processed message belongs to the currently _active_ chat (`appendChatModal.address`), the next time `appendChatModal` is called (e.g., by a timer in `handleNewMessagePolling` or by sending a message), it will pick up the newly added message(s) from the `contact.messages` array.
    - **(Current/Planned Behavior):** As noted in the `handleSendMessage` section, `appendChatModal` will use the **Hybrid Approach**: normally appending only new messages chronologically, but triggering a full re-render if an incoming message's timestamp requires it to be placed earlier than the currently last visible message.
2.  **Transaction History List (`#transactionList`):**
    - If an incoming transfer is processed (`tx.type == 'transfer'`) _and_ the Wallet screen is currently active (`#walletScreen`), it calls `updateWalletView()`.
    - `updateWalletView()` internally calls `updateTransactionHistory()`, which re-reads `myData.wallet.history` and re-renders the `#transactionList`.
3.  **Main Chat List (`#chatList`):**
    - `processChats` _itself_ doesn't directly trigger a re-render of the main `#chatList`.
    - However, if new messages were added, it modifies `myData.chats` using the correct sorted insertion logic.
    - The next time `updateChatList()` is called (e.g., by `handleNewMessagePolling`, switching screens), it will read the modified `myData.chats` array and re-render the `#chatList` entirely.
4.  **Unread Indicators / Notifications:**
    - Increments `contact.unread` which causes the unread bubble to appear in the `#chatList` upon the next render via `updateChatList()`.
    - Adds `.has-notification` class to the 'Chats' or 'Wallet' tab buttons if the respective screen isn't active, providing a visual cue for new activity.
    - (Planned) Will trigger toast notifications (currently commented out or not fully implemented for incoming messages/transfers).

**Summary:** `processChats` is the primary function for handling incoming data. It updates message lists, transaction history, and the main chat list data. UI updates are triggered either directly if the relevant screen (Wallet) is active, or indirectly via polling/other events that call functions like `appendChatModal` or `updateChatList`, which then read the modified data arrays. It also manages unread counts and notification indicators.

## Pseudocode for `insertSorted`

```javascript
/**
 * Inserts an item into an array while maintaining descending order based on a timestamp field.
 *
 * @param {Array<Object>} array - The array to insert into (e.g., myData.chats, contact.messages, myData.wallet.history).
 * @param {Object} item - The item to insert (e.g., chatUpdate, newMessage, newPayment).
 * @param {string} [timestampField='timestamp'] - The name of the field containing the timestamp to sort by.
 */
function insertSorted(array, item, timestampField = "timestamp") {
  // Find the index where the new item's timestamp is greater than or equal to
  // the timestamp of an existing item. Since we want descending order (newest first),
  // we look for the first item that is OLDER than the new item.
  const index = array.findIndex(
    (existingItem) => existingItem[timestampField] < item[timestampField]
  );

  if (index === -1) {
    // If no older item is found, the new item is the oldest, so append it to the end.
    array.push(item);
  } else {
    // Otherwise, insert the new item at the found index to maintain descending order.
    array.splice(index, 0, item);
  }
}
```
