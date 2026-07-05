import 'package:socket_io_client/socket_io_client.dart' as io;

import '../../core/config.dart';

class TrainerSocketService {
  io.Socket? _socket;

  io.Socket connect(String token) {
    final socket = io.io(
      TrainerConfig.socketBaseUrl,
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
