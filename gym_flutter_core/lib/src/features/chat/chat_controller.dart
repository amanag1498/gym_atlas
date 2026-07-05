import 'dart:async';

import 'package:flutter/foundation.dart';

import '../../core/models/app_models.dart';
import '../../core/services/realtime_service.dart';

class ChatController extends ChangeNotifier {
  ChatController({
    required this.currentUserId,
    required this.recipientId,
    required this.realtimeService,
  }) {
    _subscription = realtimeService.messages.listen(_handleIncomingMessage);
  }

  final int currentUserId;
  final int recipientId;
  final RealtimeService realtimeService;

  final List<ChatMessage> messages = <ChatMessage>[];
  StreamSubscription<ChatMessage>? _subscription;
  bool sending = false;

  void send(String text) {
    if (text.trim().isEmpty) return;
    sending = true;
    notifyListeners();
    realtimeService.sendMessage(
      recipientId: recipientId,
      message: text.trim(),
      clientMessageId: DateTime.now().microsecondsSinceEpoch.toString(),
    );
    sending = false;
    notifyListeners();
  }

  void typing(bool isTyping) {
    realtimeService.sendTyping(recipientId: recipientId, isTyping: isTyping);
  }

  void _handleIncomingMessage(ChatMessage message) {
    final relevant = (message.senderId == recipientId && message.recipientId == currentUserId) ||
        (message.senderId == currentUserId && message.recipientId == recipientId);
    if (!relevant) return;
    messages.add(message);
    notifyListeners();
  }

  @override
  Future<void> dispose() async {
    await _subscription?.cancel();
    super.dispose();
  }
}
