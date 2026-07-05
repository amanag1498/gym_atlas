import 'dart:async';

import 'package:socket_io_client/socket_io_client.dart' as io;

import '../models/app_models.dart';

class RealtimeService {
  RealtimeService({required this.baseUrl});

  final String baseUrl;
  io.Socket? _socket;

  final _messages = StreamController<ChatMessage>.broadcast();
  final _typing = StreamController<JsonMap>.broadcast();
  final _notifications = StreamController<JsonMap>.broadcast();

  Stream<ChatMessage> get messages => _messages.stream;
  Stream<JsonMap> get typing => _typing.stream;
  Stream<JsonMap> get notifications => _notifications.stream;

  bool get isConnected => _socket?.connected ?? false;

  void connect({
    required String token,
    required int currentUserId,
  }) {
    _socket?.dispose();
    _socket = io.io(
      baseUrl,
      io.OptionBuilder()
          .setTransports(['websocket'])
          .disableAutoConnect()
          .setAuth(<String, dynamic>{'token': token})
          .setExtraHeaders(<String, dynamic>{'Authorization': 'Bearer $token'})
          .build(),
    );

    _socket!
      ..on('chat:new_message', (payload) {
        final event = (payload as Map).cast<String, dynamic>();
        final message = ChatMessage.fromSocket(
          {
            ...(event['message'] as Map?)?.cast<String, dynamic>() ?? const <String, dynamic>{},
            'room': event['room'],
          },
          currentUserId: currentUserId,
        );
        _messages.add(message);
      })
      ..on('chat:typing', (payload) {
        _typing.add((payload as Map).cast<String, dynamic>());
      })
      ..on('notification:new', (payload) {
        _notifications.add((payload as Map).cast<String, dynamic>());
      })
      ..connect();
  }

  void sendMessage({
    required int recipientId,
    required String message,
    String? clientMessageId,
  }) {
    _socket?.emit('chat:send', <String, dynamic>{
      'recipientId': recipientId,
      'message': message,
      if (clientMessageId != null) 'clientMessageId': clientMessageId,
    });
  }

  void sendTyping({
    required int recipientId,
    required bool isTyping,
  }) {
    _socket?.emit('chat:typing', <String, dynamic>{
      'recipientId': recipientId,
      'isTyping': isTyping,
    });
  }

  void sendReadReceipt({
    required int recipientId,
    required List<String> messageIds,
  }) {
    _socket?.emit('chat:read', <String, dynamic>{
      'recipientId': recipientId,
      'messageIds': messageIds,
    });
  }

  void disconnect() {
    _socket?.disconnect();
    _socket?.dispose();
    _socket = null;
  }

  Future<void> dispose() async {
    disconnect();
    await _messages.close();
    await _typing.close();
    await _notifications.close();
  }
}
