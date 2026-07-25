import 'package:socket_io_client/socket_io_client.dart' as io;

import '../../core/config.dart';

class TrainerSocketService {
  io.Socket? _socket;

  io.Socket? connect(String token) {
    dispose();
    final socketUrl = TrainerConfig.socketBaseUrl.trim();
    if (socketUrl.isEmpty) {
      return null;
    }

    final socket = io.io(
      socketUrl,
      io.OptionBuilder()
          .setTransports(['websocket'])
          .disableAutoConnect()
          .setAuth({'token': token})
          .build(),
    );
    socket.connect();
    _socket = socket;
    return socket;
  }

  void dispose() {
    _socket?.disconnect();
    _socket?.dispose();
    _socket = null;
  }
}
